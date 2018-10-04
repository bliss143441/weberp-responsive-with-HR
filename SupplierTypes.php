<?php


include('includes/session.php');
$Title = _('Supplier Types') . ' / ' . _('Maintenance');
include('includes/header.php');

if (isset($_POST['SelectedType'])){
	$SelectedType = mb_strtoupper($_POST['SelectedType']);
} elseif (isset($_GET['SelectedType'])){
	$SelectedType = mb_strtoupper($_GET['SelectedType']);
}

if (isset($Errors)) {
	unset($Errors);
}

$Errors = array();

echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . _('Supplier Type Setup') . '</h1></a></div>
	';

if (isset($_POST['submit'])) {

	//initialise no input errors assumed initially before we test
	$InputError = 0;

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible
	$i=1;
	if (mb_strlen($_POST['TypeName']) >100) {
		$InputError = 1;
		echo prnMsg(_('The supplier type name description must be 100 characters or less'),'error');
		$Errors[$i] = 'SupplierType';
		$i++;
	}

	if (mb_strlen(trim($_POST['TypeName']))==0) {
		$InputError = 1;
		echo prnMsg(_('The supplier type name description must contain at least one character'),'error');
		$Errors[$i] = 'SupplierType';
		$i++;
	}

	$CheckSQL = "SELECT count(*)
		     FROM suppliertype
		     WHERE typename = '" . $_POST['TypeName'] . "'";
	$CheckResult=DB_query($CheckSQL);
	$CheckRow=DB_fetch_row($CheckResult);
	if ($CheckRow[0]>0) {
		$InputError = 1;
		echo prnMsg(_('You already have a supplier type called').' '.$_POST['TypeName'],'error');
		$Errors[$i] = 'SupplierName';
		$i++;
	}

	if (isset($SelectedType) AND $InputError !=1) {

		$sql = "UPDATE suppliertype
			SET typename = '" . $_POST['TypeName'] . "'
			WHERE typeid = '" . $SelectedType . "'";

		echo prnMsg(_('The supplier type') . ' ' . $SelectedType . ' ' .  _('has been updated'),'success');
	} elseif ($InputError !=1){
		// Add new record on submit

		$sql = "INSERT INTO suppliertype
					(typename)
				VALUES ('" . $_POST['TypeName'] . "')";


		$msg = _('Supplier type') . ' ' . $_POST['TypeName'] .  ' ' . _('has been created');
		$CheckSQL = "SELECT count(typeid) FROM suppliertype";
		$result = DB_query($CheckSQL);
		$row = DB_fetch_row($result);
	}

	if ( $InputError !=1) {
	//run the SQL from either of the above possibilites
		$result = DB_query($sql);


	// Fetch the default supplier type
		$sql = "SELECT confvalue
					FROM config
					WHERE confname='DefaultSupplierType'";
		$result = DB_query($sql);
		$SupplierTypeRow = DB_fetch_row($result);
		$DefaultSupplierType = $SupplierTypeRow[0];

	// Does it exist
		$CheckSQL = "SELECT count(*)
			     FROM suppliertype
			     WHERE typeid = '" . $DefaultSupplierType . "'";
		$CheckResult = DB_query($CheckSQL);
		$CheckRow = DB_fetch_row($CheckResult);

	// If it doesnt then update config with newly created one.
		if ($CheckRow[0] == 0) {
			$sql = "UPDATE config
					SET confvalue='" . $_POST['TypeID'] . "'
					WHERE confname='DefaultSupplierType'";
			$result = DB_query($sql);
			$_SESSION['DefaultSupplierType'] = $_POST['TypeID'];
		}

		unset($SelectedType);
		unset($_POST['TypeID']);
		unset($_POST['TypeName']);
	}

} elseif ( isset($_GET['delete']) ) {

	$sql = "SELECT COUNT(*) FROM suppliers WHERE supptype='" . $SelectedType . "'";

	$ErrMsg = _('The number of suppliers using this Type record could not be retrieved because');
	$result = DB_query($sql,$ErrMsg);
	$myrow = DB_fetch_row($result);
	if ($myrow[0]>0) {
		echo prnMsg (_('Cannot delete this type because suppliers are currently set up to use this type') . '<br />' .
			_('There are') . ' ' . $myrow[0] . ' ' . _('suppliers with this type code'));
	} else {

		$sql="DELETE FROM suppliertype WHERE typeid='" . $SelectedType . "'";
		$ErrMsg = _('The Type record could not be deleted because');
		$result = DB_query($sql,$ErrMsg);
		echo prnMsg(_('Supplier type') . $SelectedType  . ' ' . _('has been deleted') ,'success');

		unset ($SelectedType);
		unset($_GET['delete']);

	}
}

if (!isset($SelectedType)){

/* It could still be the second time the page has been run and a record has been selected for modification - SelectedType will
 *  exist because it was sent with the new call. If its the first time the page has been displayed with no parameters then
 * none of the above are true and the list of sales types will be displayed with links to delete or edit each. These will call
 * the same page again and allow update/input or deletion of the records
 */

	$sql = "SELECT typeid, typename FROM suppliertype";
	$result = DB_query($sql);

	echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
		<thead>
			<tr>
		<th class="ascending" >' . _('Type ID') . '</th>
		<th class="ascending" >' . _('Type Name') . '</th>
		<th colspan="2" >' . _('Actions') . '</th>
			</tr>
		</thead>
		<tbody>';

while ($myrow = DB_fetch_row($result)) {

	printf('<tr class="striped_row">
			<td>%s</td>
			<td>%s</td>
			<td><a href="%sSelectedType=%s" class="btn btn-info">' . _('Edit') . '</a></td>
			<td><a href="%sSelectedType=%s&amp;delete=yes" onclick="return confirm(\'' .
				_('Are you sure you wish to delete this Supplier Type?') . '\');" class="btn btn-danger">' . _('Delete') . '</a></td>
		</tr>',
		$myrow[0],
		$myrow[1],
		htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
		$myrow[0],
		htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
		$myrow[0]);
	}
	//END WHILE LIST LOOP
	echo '</tbody></table></div></div></div>';
}

//end of ifs and buts!
if (isset($SelectedType)) {

	echo '<div class="row">
	<div class="col-xs-4">
			<a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" class="btn btn-info">' . _('Show All Types Defined') . '</a>
		</div></div><br />
';
}
if (! isset($_GET['delete'])) {
echo '<div class="row gutter30">
<div class="col-xs-12">';
	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">
	
	';

	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<br />
		<div class="row">'; //Main table

	// The user wish to EDIT an existing type
	if ( isset($SelectedType) AND $SelectedType!='' ) {

		$sql = "SELECT typeid,
			       typename
		        FROM suppliertype
		        WHERE typeid='" . $SelectedType . "'";

		$result = DB_query($sql);
		$myrow = DB_fetch_array($result);

		$_POST['TypeID'] = $myrow['typeid'];
		$_POST['TypeName']  = $myrow['typename'];

		echo '<input type="hidden" name="SelectedType" value="' . $SelectedType . '" />';
		echo '<input type="hidden" name="TypeID" value="' . $_POST['TypeID'] . '" />';

		// We dont allow the user to change an existing type code

		echo '
<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">' ._('Type ID') . ' </label>
				' . $_POST['TypeID'] . '</div>
			</div>';
	}

	if (!isset($_POST['TypeName'])) {
		$_POST['TypeName']='';
	}
	echo '
<div class="col-xs-4">
<div class="form-group has-error"> <label class="col-md-8 control-label">' . _('Type Name') . '</label>
			<input type="text" class="form-control" required="true" pattern="(?!^\s+$)[^<>+-]{1,100}" title="'._('The input should not be over 100 characters and contains illegal characters').'" name="TypeName" placeholder="'._('less than 100 characters').'" value="' . $_POST['TypeName'] . '" /></div>
		</div>';

	echo '
<div class="col-xs-4">
<div class="form-group"> <br />
					<input type="submit" name="submit" value="' . _('Enter Information') . '" class="btn btn-success" />
				</div>
			</div>
		</div>
		
		
		</form></div></div>';

} // end if user wish to delete

include('includes/footer.php');
?>
