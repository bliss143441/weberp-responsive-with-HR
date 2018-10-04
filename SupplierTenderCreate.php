<?php

include('includes/DefineTenderClass.php');
include('includes/SQL_CommonFunctions.inc');
include('includes/session.php');

if (empty($_GET['identifier'])) {
	/*unique session identifier to ensure that there is no conflict with other supplier tender sessions on the same machine  */
	$identifier=date('U');
} else {
	$identifier=$_GET['identifier'];
}

if (isset($_GET['New']) and isset($_SESSION['tender'.$identifier])) {
	unset($_SESSION['tender'.$identifier]);
}

if (isset($_GET['New']) and $_SESSION['CanCreateTender']==0) {
	$Title = _('Authorisation Problem');
	include('includes/header.php');
	echo '<div class="block-header"><a href="" class="header-title-link"><h1> '.$Title . '</h1></a></div>';
	echo   
prnMsg( _('You do not have authority to create supplier tenders for this company.') . '. ' .
			_('Please see your system administrator'), 'warn');
	include('includes/footer.php');
	exit;
}

if (isset($_GET['Edit']) and $_SESSION['CanCreateTender']==0) {
	$Title = _('Authorisation Problem');
	include('includes/header.php');
	echo '<div class="block-header"><a href="" class="header-title-link"><h1> '.$Title . '</h1></a></div>';
	echo   
prnMsg( _('You do not have authority to amend supplier tenders for this company.') . '. ' .
			_('Please see your system administrator'), 'warn');
	include('includes/footer.php');
	exit;
}

if (isset($_POST['Close'])) {
	$SQL = "UPDATE tenders SET closed=1 WHERE tenderid='" . $_SESSION['tender'.$identifier]->TenderId . "'";
	$Result = DB_query($SQL);
	$_GET['Edit'] = 'Yes';
	unset($_SESSION['tender'.$identifier]);
}

$ShowTender = 0;

if (isset($_GET['ID'])) {
	$sql = "SELECT tenderid,
					location,
					address1,
					address2,
					address3,
					address4,
					address5,
					address6,
					telephone,
					requiredbydate
				FROM tenders
				INNER JOIN locationusers ON locationusers.loccode=tenders.location AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
				WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql);
	$myrow=DB_fetch_array($result);
	if (isset($_SESSION['tender'.$identifier])) {
		unset($_SESSION['tender'.$identifier]);
	}
	$_SESSION['tender'.$identifier] = new Tender();
	$_SESSION['tender'.$identifier]->TenderId = $myrow['tenderid'];
	$_SESSION['tender'.$identifier]->Location = $myrow['location'];
	$_SESSION['tender'.$identifier]->DelAdd1 = $myrow['address1'];
	$_SESSION['tender'.$identifier]->DelAdd2 = $myrow['address2'];
	$_SESSION['tender'.$identifier]->DelAdd3 = $myrow['address3'];
	$_SESSION['tender'.$identifier]->DelAdd4 = $myrow['address4'];
	$_SESSION['tender'.$identifier]->DelAdd5 = $myrow['address5'];
	$_SESSION['tender'.$identifier]->DelAdd6 = $myrow['address6'];
	$_SESSION['tender'.$identifier]->RequiredByDate = $myrow['requiredbydate'];

	$sql = "SELECT tenderid,
					tendersuppliers.supplierid,
					suppliers.suppname,
					tendersuppliers.email
				FROM tendersuppliers
				LEFT JOIN suppliers
					ON tendersuppliers.supplierid=suppliers.supplierid
				WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql);
	while ($myrow=DB_fetch_array($result)) {
		$_SESSION['tender'.$identifier]->add_supplier_to_tender($myrow['supplierid'],
																$myrow['suppname'],
																$myrow['email']);
	}

	$sql = "SELECT tenderid,
					tenderitems.stockid,
					tenderitems.quantity,
					stockmaster.description,
					tenderitems.units,
					stockmaster.decimalplaces
				FROM tenderitems
				LEFT JOIN stockmaster
					ON tenderitems.stockid=stockmaster.stockid
				WHERE tenderid='" . $_GET['ID'] . "'";
	$result=DB_query($sql);
	while ($myrow=DB_fetch_array($result)) {
		$_SESSION['tender'.$identifier]->add_item_to_tender($_SESSION['tender'.$identifier]->LinesOnTender,
															$myrow['stockid'],
															$myrow['quantity'],
															$myrow['description'],
															$myrow['units'],
															$myrow['decimalplaces'],
															DateAdd(date($_SESSION['DefaultDateFormat']),'m',3));
	}
	$ShowTender = 1;
}

if (isset($_GET['Edit'])) {
	$Title = _('Edit an Existing Supplier Tender Request');
	include('includes/header.php');
	echo '<div class="block-header"><a href="" class="header-title-link"><h1> '.$Title . '</h1></a></div>';
	$sql = "SELECT tenderid,
					location,
					address1,
					address2,
					address3,
					address4,
					address5,
					address6,
					telephone
				FROM tenders
				INNER JOIN locationusers ON locationusers.loccode=tenders.location AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canupd=1
				WHERE closed=0
					AND requiredbydate > '" . Date('Y-m-d') . "'";
	$result=DB_query($sql);
	echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
';
	echo '<tr>
			<th>' . _('Tender ID') . '</th>
			<th>' . _('Location') . '</th>
			<th>' . _('Address 1') . '</th>
			<th>' . _('Address 2') . '</th>
			<th>' . _('Address 3') . '</th>
			<th>' . _('Address 4') . '</th>
			<th>' . _('Address 5') . '</th>
			<th>' . _('Address 6') . '</th>
			<th>' . _('Telephone') . '</th>
		</tr>';
	while ($myrow=DB_fetch_array($result)) {
		echo '<tr>
				<td>' . $myrow['tenderid'] . '</td>
				<td>' . $myrow['location'] . '</td>
				<td>' . $myrow['address1'] . '</td>
				<td>' . $myrow['address2'] . '</td>
				<td>' . $myrow['address3'] . '</td>
				<td>' . $myrow['address4'] . '</td>
				<td>' . $myrow['address5'] . '</td>
				<td>' . $myrow['address6'] . '</td>
				<td>' . $myrow['telephone'] . '</td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?identifier=' . $identifier . '&amp;ID=' . $myrow['tenderid'] . '" class="btn btn-info">' . _('Edit') . '</a></td>
			</tr>';
	}
	echo '</table></div></div></div>';
	include('includes/footer.php');
	exit;
} else if (isset($_GET['ID']) or (isset($_SESSION['tender'.$identifier]->TenderId))) {
	$Title = _('Edit an Existing Supplier Tender Request');
	include('includes/header.php');
	echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . $Title . '</h1></a></div>';
} else {
	$Title = _('Create a New Supplier Tender Request');
	include('includes/header.php');
	echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . $Title . '</h1></a></div>';
}

if (isset($_POST['Save'])) {
	$_SESSION['tender'.$identifier]->RequiredByDate=$_POST['RequiredByDate'];
	$_SESSION['tender'.$identifier]->save();
	$_SESSION['tender'.$identifier]->EmailSuppliers();
	echo  
prnMsg( _('The tender has been successfully saved'), 'success');
	include('includes/footer.php');
	exit;
}

if (isset($_GET['DeleteSupplier'])) {
	$_SESSION['tender'.$identifier]->remove_supplier_from_tender($_GET['DeleteSupplier']);
	$ShowTender = 1;
}

if (isset($_GET['DeleteItem'])) {
	$_SESSION['tender'.$identifier]->remove_item_from_tender($_GET['DeleteItem']);
	$ShowTender = 1;
}

if (isset($_POST['SelectedSupplier'])) {
	$sql = "SELECT suppname,
					email
				FROM suppliers
				WHERE supplierid='" . $_POST['SelectedSupplier'] . "'";
	$result = DB_query($sql);
	$myrow = DB_fetch_array($result);
	if (mb_strlen($myrow['email'])>0) {
		$_SESSION['tender'.$identifier]->add_supplier_to_tender($_POST['SelectedSupplier'],
																$myrow['suppname'],
																$myrow['email']);
	} else {
		echo   
prnMsg( _('The supplier must have an email set up or they cannot be part of a tender'), 'warn');
	}
	$ShowTender = 1;
}

if (isset($_POST['NewItem']) and !isset($_POST['Refresh'])) {
	foreach ($_POST as $key => $value) {
		if (mb_substr($key,0,7)=='StockID') {
			$Index = mb_substr($key,7,mb_strlen($key)-7);
			$StockID = $value;
			$Quantity = filter_number_format($_POST['Qty'.$Index]);
			$UOM = $_POST['UOM'.$Index];
			$sql = "SELECT description,
							decimalplaces
						FROM stockmaster
						WHERE stockid='".$StockID."'";
			$result=DB_query($sql);
			$myrow=DB_fetch_array($result);
			$_SESSION['tender'.$identifier]->add_item_to_tender($_SESSION['tender'.$identifier]->LinesOnTender,
																$StockID,
																$Quantity,
																$myrow['description'],
																$UOM,
																$myrow['decimalplaces'],
																DateAdd(date($_SESSION['DefaultDateFormat']),'m',3));
			unset($UOM);
		}
	}
	$ShowTender = 1;
}

if (!isset($_SESSION['tender'.$identifier])
	or isset($_POST['LookupDeliveryAddress'])
	or $ShowTender==1) {

	/* Show Tender header screen */
	if (!isset($_SESSION['tender'.$identifier])) {
		$_SESSION['tender'.$identifier]=new Tender();
	}
	if (!isset($_SESSION['tender'.$identifier]->RequiredByDate)) {
		$_SESSION['tender'.$identifier]->RequiredByDate = FormatDateForSQL(date($_SESSION['DefaultDateFormat']));
	}
	echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="block">
<div class="block-title"><h2>' . _('Tender header details') . '</h2></div>';
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?identifier='.$identifier . '" method="post" class="noPrint">';
	
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<div class="row">
		<div class="col-xs-4">
        <div class="form-group has-error"> <label class="col-md-8 control-label">
' . _('Delivery Date') . '</label>
			<input type="text" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker"
 required="required" name="RequiredByDate" size="11" value="' . ConvertSQLDate($_SESSION['tender'.$identifier]->RequiredByDate) . '" /></div>
		</div>';

	if (!isset($_POST['StkLocation']) or $_POST['StkLocation']==''){
	/* If this is the first time
	* the form loaded set up defaults */

		$_POST['StkLocation'] = $_SESSION['UserStockLocation'];

		$sql = "SELECT deladd1,
						deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					INNER JOIN locationusers ON locationusers.loccode=.locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canupd=1
					WHERE locations.loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql);
		if (DB_num_rows($LocnAddrResult)==1){
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['tender'.$identifier]->Location= $_POST['StkLocation'];
			$_SESSION['tender'.$identifier]->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['tender'.$identifier]->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['tender'.$identifier]->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['tender'.$identifier]->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['tender'.$identifier]->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['tender'.$identifier]->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['tender'.$identifier]->Telephone = $_POST['Tel'];
			$_SESSION['tender'.$identifier]->Contact = $_POST['Contact'];

		} else {
			 /*The default location of the user is crook */
			echo   
prnMsg(_('The default stock location set up for this user is not a currently defined stock location') .
				'. ' . _('Your system administrator needs to amend your user record'),'error');
		}


	} elseif (isset($_POST['LookupDeliveryAddress'])){

		$sql = "SELECT deladd1,
						deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					INNER JOIN locationusers ON locationusers.loccode=.locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canupd=1
					WHERE loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql);
		if (DB_num_rows($LocnAddrResult)==1){
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['tender'.$identifier]->Location= $_POST['StkLocation'];
			$_SESSION['tender'.$identifier]->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['tender'.$identifier]->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['tender'.$identifier]->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['tender'.$identifier]->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['tender'.$identifier]->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['tender'.$identifier]->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['tender'.$identifier]->Telephone = $_POST['Tel'];
			$_SESSION['tender'.$identifier]->Contact = $_POST['Contact'];
		}
	}
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Location') . '</label>
			<select name="StkLocation" class="form-control" onchange="ReloadForm(form1.LookupDeliveryAddress)">';

	$sql = "SELECT locations.loccode,
					locationname
				FROM locations
				INNER JOIN locationusers ON locationusers.loccode=.locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canupd=1";
	$LocnResult = DB_query($sql);

	while ($LocnRow=DB_fetch_array($LocnResult)){
		if ((isset($_SESSION['tender'.$identifier]->Location) and $_SESSION['tender'.$identifier]->Location == $LocnRow['loccode'])){
			echo '<option selected="selected" value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		} else {
			echo '<option value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		}
	}

	echo '</select>
		<input type="submit" class="btn btn-default name="LookupDeliveryAddress" value="' ._('Select from above') . '" /></div>
		</div>';

	/* Display the details of the delivery location
	 */
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Delivery Contact') . '</label>
			<input type="text" class="form-control" name="Contact" size="41"  value="' . $_SESSION['tender'.$identifier]->Contact . '" readonly /></div>
		</div></div>';
	echo '<div class="row">
			<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 1 </label>
			<input type="text" class="form-control" name="DelAdd1" pattern=".{1,40}" title="'._('The address should not be over 40 characters').'" size="41" maxlength="40" value="' . $_POST['DelAdd1'] . '" /></div>
		</div>';
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 2 </label>
			<input type="text" class="form-control" name="DelAdd2" pattern=".{1,40}" title="'._('The address should not be over 40 characters').'" size="41" size="41" maxlength="40" value="' . $_POST['DelAdd2'] . '" /></div>
		</div>';
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 3 </label>
			<input type="text" class="form-control" name="DelAdd3" pattern=".{1,40}" title="'._('The address should not be over 40 characters').'" size="41" size="41" maxlength="40" value="' . $_POST['DelAdd3'] . '" /></div>
		</div></div>';
	echo '<div class="row">
			<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 4 </label>
			<input type="text" class="form-control" name="DelAdd4" pattern=".{1,40}" title="'._('The characters should not be over 20 characters').'"  size="41" maxlength="40" value="' . $_POST['DelAdd4'] . '" /></div>
		</div>';
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 5 </label>
			<input type="text" class="form-control" name="DelAdd5" pattern=".{1,20}" title="'._('The characters should not be over 20 characters').'" size="21" maxlength="20" value="' . $_POST['DelAdd5'] . '" /></div>
		</div>';
	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Address') . ' 6 </label>
			<input type="text" class="form-control" name="DelAdd6" pattern=".{1,15}" title="'._('The characters should not be over 15 characters').'"  size="16" maxlength="15" value="' . $_POST['DelAdd6'] . '" /></div>
		</div></div>';
	echo '<div class="row">
			<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Phone') . '</label>
			<input type="tel" class="form-control" name="Tel" pattern="[\d+)(\s]{1,25}" size="31" title="'._('The input should be telephone number and should not be over 25 charaters').'" maxlength="25" value="' . $_SESSION['tender'.$identifier]->Telephone . '" /></div>
		</div>';
	echo '</div>';

	/* Display the supplier/item details
	 */
	

	/* Supplier Details
	 */
	echo '<div class="col-xs-6">
<div class="block">
<div class="block-title"><h2>' . _('Suppliers To Send Tender') . '</h2></div>
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
';
	
	echo '<thead>
	<tr>
			<th>' .  _('Supplier Code') . '</th>
			<th>' ._('Supplier Name') . '</th>
			<th>' ._('Email Address') . '</th>
			<th>' ._('Action') . '</th>
		</tr></thead>';
	foreach ($_SESSION['tender'.$identifier]->Suppliers as $Supplier) {
		echo '<tr>
				<td>' . $Supplier->SupplierCode . '</td>
				<td>' . $Supplier->SupplierName . '</td>
				<td>' . $Supplier->EmailAddress . '</td>
				<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier, ENT_QUOTES,'UTF-8') . '&amp;DeleteSupplier=' . $Supplier->SupplierCode . '">' . _('Delete') . '</a></td>
			</tr>';
	}
	echo '</table></div></div></div>';
	/* Item Details
	 */
	echo '<div class="col-xs-6">
	<div class="block">
<div class="block-title"><h2>' . _('Items in Tender') . '</h2></div>
<div class="table-responsive">
<table id="general-table" class="table table-bordered">

		<thead>
		<tr>
			<th class="ascending">' . _('Stock ID') . '</th>
			<th class="ascending">' . _('Description') . '</th>
			<th class="ascending">' . _('Quantity') . '</th>
			<th>' . _('UOM') . '</th>
			<th>' . _('Action') . '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($_SESSION['tender'.$identifier]->LineItems as $LineItems) {
		if ($LineItems->Deleted==False) {
			echo '<tr class="striped_row">
					<td>' . $LineItems->StockID . '</td>
					<td>' . $LineItems->ItemDescription . '</td>
					<td class="number">' . locale_number_format($LineItems->Quantity,$LineItems->DecimalPlaces) . '</td>
					<td>' . $LineItems->Units . '</td>
					<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier,ENT_QUOTES,'UTF-8') . '&amp;DeleteItem=' . $LineItems->LineNo . '">' . _('Delete') . '</a></td>
				</tr>';
		}
	}
	echo '</tbody></table>
		</div>
		</div>
		</div>
		
		<div class="row">
		<div class="col-xs-6">
			<input type="submit" name="Suppliers" class="btn btn-info" value="' . _('Select Suppliers') . '" />
			</div>
		<div class="col-xs-4">	
			<input type="submit" name="Items" class="btn btn-info"" value="' . _('Select Items') . '" /></div></div>';

	if ($_SESSION['tender'.$identifier]->LinesOnTender > 0
		and $_SESSION['tender'.$identifier]->SuppliersOnTender > 0) {
		echo '<div class="col-xs-4"><input type="submit" name="Close" class="btn btn-danger" value="' . _('Close This Tender') . '" /></div>';
	}
	echo '</div>
		<br />';
	if ($_SESSION['tender'.$identifier]->LinesOnTender > 0
		and $_SESSION['tender'.$identifier]->SuppliersOnTender > 0) {

		echo '<div class="row">
		<div class="col-xs-4">	
				<input type="submit" class="btn btn-success" name="Save" value="' . _('Save Tender') . '" />
			</div>
			</div>';
	}
	echo '
		</form>
		</div>
		</div>
		';
	include('includes/footer.php');
	exit;
}

if (isset($_POST['SearchSupplier']) or isset($_POST['Go'])
	or isset($_POST['Next']) or isset($_POST['Previous'])) {

	if (mb_strlen($_POST['Keywords']) > 0 and mb_strlen($_POST['SupplierCode']) > 0) {
		echo    
prnMsg( '<br />' . _('Supplier name keywords have been used in preference to the Supplier code extract entered'), 'info' ),'</div>';
	}
	if ($_POST['Keywords'] == '' and $_POST['SupplierCode'] == '') {
		$SQL = "SELECT supplierid,
						suppname,
						currcode,
						address1,
						address2,
						address3,
						address4
					FROM suppliers
					WHERE email<>''
					ORDER BY suppname";
	} else {
		if (mb_strlen($_POST['Keywords']) > 0) {
			$_POST['Keywords'] = mb_strtoupper($_POST['Keywords']);
			//insert wildcard characters in spaces
			$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';
			$SQL = "SELECT supplierid,
							suppname,
							currcode,
							address1,
							address2,
							address3,
							address4
						FROM suppliers
						WHERE suppname " . LIKE . " '$SearchString'
							AND email<>''
						ORDER BY suppname";
		} elseif (mb_strlen($_POST['SupplierCode']) > 0) {
			$_POST['SupplierCode'] = mb_strtoupper($_POST['SupplierCode']);
			$SQL = "SELECT supplierid,
							suppname,
							currcode,
							address1,
							address2,
							address3,
							address4
						FROM suppliers
						WHERE supplierid " . LIKE . " '%" . $_POST['SupplierCode'] . "%'
							AND email<>''
						ORDER BY supplierid";
		}
	} //one of keywords or SupplierCode was more than a zero length string
	$result = DB_query($SQL);
	if (DB_num_rows($result) == 1) {
		$myrow = DB_fetch_array($result);
		$SingleSupplierReturned = $myrow['supplierid'];
	}
} //end of if search
if (isset($SingleSupplierReturned)) { /*there was only one supplier returned */
	$_SESSION['SupplierID'] = $SingleSupplierReturned;
	unset($_POST['Keywords']);
	unset($_POST['SupplierCode']);
}

if (!isset($_POST['PageOffset'])) {
	$_POST['PageOffset'] = 1;
} else {
	if ($_POST['PageOffset'] == 0) {
		$_POST['PageOffset'] = 1;
	}
}

if (isset($_POST['Suppliers'])) {
	
	echo '<div class="row gutter30">
<div class="col-xs-12">';
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier, ENT_QUOTES,'UTF-8') . '" method="post" class="noPrint">';
	
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	
	
		echo '<div class="row">
		<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">
' . _('Supplier Name-part or full') . '</label>
				';
	if (isset($_POST['Keywords'])) {
		echo '<input type="text" class="form-control" placeholder="'._('Left it blank to show all').'" name="Keywords" value="' . $_POST['Keywords'] . '" size="20" maxlength="25" />';
	} else {
		echo '<input type="text" class="form-control" placeholder="'._('Left it blank to show all').'" name="Keywords" size="20" maxlength="25" />';
	}
	echo '</div></div>
	<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Supplier Code-part or full') . '</label>';
	if (isset($_POST['SupplierCode'])) {
		echo '<input type="text" class="form-control" placeholder="'._('Left it blank to show all').'" name="SupplierCode" value="' . $_POST['SupplierCode'] . '" size="15" maxlength="18" />';
	} else {
		echo '<input type="text" class="form-control" placeholder="'._('Left it blank to show all').'" name="SupplierCode" size="15" maxlength="18" />';
	}
	echo '</div></div>
	<div class="col-xs-4">
        <div class="form-group"><br />
	<input type="submit" name="SearchSupplier" value="' . _('Search') . '" class="btn btn-success" /></div>';
	echo '</div></div>
		</form></div></div>';
}

if (isset($_POST['SearchSupplier'])) {
	echo '<div class="row gutter30">
<div class="col-xs-12">';

	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier, ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';

	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	$ListCount = DB_num_rows($result);
	$ListPageMax = ceil($ListCount / $_SESSION['DisplayRecordsMax']);
	if (isset($_POST['Next'])) {
		if ($_POST['PageOffset'] < $ListPageMax) {
			$_POST['PageOffset'] = $_POST['PageOffset'] + 1;
		}
	}
	if (isset($_POST['Previous'])) {
		if ($_POST['PageOffset'] > 1) {
			$_POST['PageOffset'] = $_POST['PageOffset'] - 1;
		}
	}
	if ($ListPageMax > 1) {
		echo '<div class="row">
		<div class="col-xs-3">
        <div class="form-group"> <label class="col-md-8 control-label">
' . $_POST['PageOffset'] . ' ' . _('of') . ' ' . $ListPageMax . ' ' . _('pages') . '. ' . _('Go to Page') . '</label> ';
		echo '<select name="PageOffset" class="form-control">';
		$ListPage = 1;
		while ($ListPage <= $ListPageMax) {
			if ($ListPage == $_POST['PageOffset']) {
				echo '<option value="' . $ListPage . '" selected="selected">' . $ListPage . '</option>';
			} else {
				echo '<option value="' . $ListPage . '">' . $ListPage . '</option>';
			}
			$ListPage++;
		}
		echo '</select></div></div>
			<div class="col-xs-3">
        <div class="form-group"><br /><input type="submit" class="btn btn-success" name="Go" value="' . _('Go') . '" />
		</div>
		</div>
		<div class="col-xs-3">
        <div class="form-group"><br />
			<input type="submit" class="btn btn-default" name="Previous" value="' . _('Previous') . '" />
			</div>
		</div>
			<div class="col-xs-3">
        <div class="form-group"><br />
			<input type="submit" class="btn btn-default" name="Next" value="' . _('Next') . '" />';
		echo '</div>
		</div></div>';
	}
	echo '<input type="hidden" name="Search" class="btn btn-success" value="' . _('Search') . '" />';
	echo '
		<br />
		<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
';
	echo '<thead><tr>
	  		<th class="assending">' . _('Supplier Code') . '</th>
			<th class="assending">' . _('Supplier Name') . '</th>
			<th class="assending">' . _('Currency') . '</th>
			<th class="assending">' . _('Address 1') . '</th>
			<th class="assending">' . _('Address 2') . '</th>
			<th class="assending">' . _('Address 3') . '</th>
			<th class="assending">' . _('Address 4') . '</th>
		</tr></thead>';
	$j = 1;
	$RowIndex = 0;
	if (DB_num_rows($result) <> 0) {
		DB_data_seek($result, ($_POST['PageOffset'] - 1) * $_SESSION['DisplayRecordsMax']);
	}else{
		prnMsg(_('There are no suppliers data returned, one reason maybe no email addresses set for those suppliers'),'warn');
	}
	while (($myrow = DB_fetch_array($result)) and ($RowIndex <> $_SESSION['DisplayRecordsMax'])) {
		echo '<tr class="striped_row">
			<td><input type="submit" name="SelectedSupplier" value="'.$myrow['supplierid'].'" /></td>
			<td>' . $myrow['suppname'] . '</td>
			<td>' . $myrow['currcode'] . '</td>
			<td>' . $myrow['address1'] . '</td>
			<td>' . $myrow['address2'] . '</td>
			<td>' . $myrow['address3'] . '</td>
			<td>' . $myrow['address4'] . '</td>
			</tr>';
		$RowIndex = $RowIndex + 1;
		//end of page full new headings if
	}
	//end of while loop
	echo '</table>';
	echo '</div></div>
		</form>
		</div>
		</div>
		';
}

/*The supplier has chosen option 2
 */
if (isset($_POST['Items'])) {
	echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . ' ' . _('Search for Inventory Items') . '</h1></a></div>';
	echo '<div class="row gutter30">
<div class="col-xs-12">';
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier, ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
	
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	
	$sql = "SELECT categoryid,
				categorydescription
			FROM stockcategory
			ORDER BY categorydescription";
	$result = DB_query($sql);
	if (DB_num_rows($result) == 0) {
		echo '<br /><p class="text-danger">' . _('Problem Report') . ':' .
			_('There are no stock categories currently defined please use the link below to set them up');
		echo '</p><br /><p align="right"><a href="' . $RootPath . '/StockCategories.php" class="btn btn-info">' . _('Define Stock Categories') . '</a></p>';
		exit;
	}
	echo '<div class="row">
		<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">
' . _('Stock Category') . '</label>
<select name="StockCat" class="form-control">';
	if (!isset($_POST['StockCat'])) {
		$_POST['StockCat'] = '';
	}
	if ($_POST['StockCat'] == 'All') {
		echo '<option selected="selected" value="All">' . _('All') . '</option>';
	} else {
		echo '<option value="All">' . _('All') . '</option>';
	}
	while ($myrow1 = DB_fetch_array($result)) {
		if ($myrow1['categoryid'] == $_POST['StockCat']) {
			echo '<option selected="selected" value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'] . '</option>';
		} else {
			echo '<option value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'] . '</option>';
		}
	}
	echo '</select></div></div>
	
		
		<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">
' . _('Description') . ' ' . _('-part or full') . '</label>
		';
	if (isset($_POST['Keywords'])) {
		echo '<input type="text" class="form-control" name="Keywords" placeholder="'._('Leave it bank to show all').'" value="' . $_POST['Keywords'] . '" size="20" maxlength="25" />';
	} else {
		echo '<input type="text" class="form-control" name="Keywords" placeholder="'._('Leave it bank to show all').'" size="20" maxlength="25" />';
	}
	echo '</div>
		</div>
		
		<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">
' . _('Stock Code') . ' ' . _('-part or full') . '</label>
			';
	if (isset($_POST['StockCode'])) {
		echo '<input type="text" class="form-control" name="StockCode" placeholder="'._().'" autofocus="autofocus" value="' . $_POST['StockCode'] . '" size="15" maxlength="18" />';
	} else {
		echo '<input type="text" class="form-control" name="StockCode" placeholder="'._().'" autofocus="autofocus"  size="15" maxlength="18" />';
	}
	echo '</div></div>
		</div>
		
		<div class="row">
		<div class="col-xs-4">
        <div class="form-group">
			<input type="submit" name="Search" class="btn btn-success" value="' . _('Search') . '" />
		</div>
		</div>
		</div>
		</form>
		</div>
		</div>
		';
}

if (isset($_POST['Search'])){  /*ie seach for stock items */

echo '<div class="row gutter30">
<div class="col-xs-12">';
	echo '<form method="post" class="noPrint" action="' . htmlspecialchars($_SERVER['PHP_SELF'].'?identifier='.$identifier,ENT_QUOTES,'UTF-8') .'">';
	
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	if ($_POST['Keywords'] and $_POST['StockCode']) {
		echo   
prnMsg( _('Stock description keywords have been used in preference to the Stock code extract entered'), 'info' ),'</div>';
	}
	if ($_POST['Keywords']) {
		//insert wildcard characters in spaces
		$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					AND stockmaster.description " . LIKE . " '$SearchString'
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		} else {
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					AND stockmaster.description " . LIKE . " '$SearchString'
					AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		}

	} elseif ($_POST['StockCode']){

		$_POST['StockCode'] = '%' . $_POST['StockCode'] . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		} else {
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
					AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		}

	} else {
		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		} else {
			$sql = "SELECT stockmaster.stockid,
						stockmaster.description,
						stockmaster.units
					FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
					WHERE stockmaster.mbflag!='D'
					AND stockmaster.mbflag!='A'
					AND stockmaster.mbflag!='K'
					AND stockmaster.mbflag!='G'
					AND stockmaster.discontinued!=1
					AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
					ORDER BY stockmaster.stockid
					LIMIT " . $_SESSION['DisplayRecordsMax'];
		}
	}

	$ErrMsg = _('There is a problem selecting the part records to display because');
	$DbgMsg = _('The SQL statement that failed was');
	$SearchResult = DB_query($sql,$ErrMsg,$DbgMsg);

	if (DB_num_rows($SearchResult)==0 and $debug==1){
		echo   
prnMsg( _('There are no products to display matching the criteria provided'),'warn');
	}
	if (DB_num_rows($SearchResult)==1){

		$myrow=DB_fetch_array($SearchResult);
		$_GET['NewItem'] = $myrow['stockid'];
		DB_data_seek($SearchResult,0);
	}

	if (isset($SearchResult)) {

		echo '<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">';
		echo '<thead><tr>
				<th class="assending">' . _('Code')  . '</th>
				<th class="assending">' . _('Description') . '</th>
				<th class="assending">' . _('Units') . '</th>
				<th class="assending">' . _('Image') . '</th>
				<th class="assending">' . _('Quantity') . '</th>
			</tr></thead>';

		$i = 0;
		$PartsDisplayed=0;
		while ($myrow=DB_fetch_array($SearchResult)) {

			$SupportedImgExt = array('png','jpg','jpeg');
			$imagefile = reset((glob($_SESSION['part_pics_dir'] . '/' . $myrow['stockid'] . '.{' . implode(",", $SupportedImgExt) . '}', GLOB_BRACE)));
			if (extension_loaded('gd') && function_exists('gd_info') && file_exists ($imagefile) ) {
				$ImageSource = '<img src="GetStockImage.php?automake=1&amp;textcolor=FFFFFF&amp;bgcolor=CCCCCC'.
					'&amp;StockID='.urlencode($myrow['stockid']).
					'&amp;text='.
					'&amp;width=64'.
					'&amp;height=64'.
					'" alt="" />';
			} else if (file_exists ($imagefile)) {
				$ImageSource = '<img src="' . $imagefile . '" height="64" width="64" />';
 			} else {
				$ImageSource = _('No Image');
 			}

			echo '<tr class="striped_row">
					<td>' . $myrow['stockid'] . '</td>
					<td>' . $myrow['description'] . '</td>
					<td>' . $myrow['units'] . '</td>
					<td>' . $ImageSource . '</td>
					<td><input class="form-control" type="text" size="6" value="0" name="Qty'.$i.'" /></td>
					<input type="hidden" value="'.$myrow['units'].'" name="UOM'.$i.'" />
					<input type="hidden" value="'.$myrow['stockid'].'" name="StockID'.$i.'" />
					</tr>';

			$i++;
#end of page full new headings if
		}
#end of while loop
		echo '</table></div></div>';

		echo '<a name="end"></a>
			<br />
			<div class="row" align="center">
			<div>
				<input type="submit" class="btn btn-success" name="NewItem" value="' . _('Add to Tender') . '" />
			</div></div>';
	}#end if SearchResults to show

	echo '
		</form>
		</div>
		</div>
		';

} //end of if search

include('includes/footer.php');

?>
