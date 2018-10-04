<?php


include('includes/DefineJournalClass.php');

include('includes/session.php');
$Title = _('Journal Entry');

$ViewTopic = 'GeneralLedger';
$BookMark = 'GLJournals';

include('includes/header.php');
include('includes/SQL_CommonFunctions.inc');

if (isset($_GET['NewJournal'])
	AND $_GET['NewJournal'] == 'Yes'
	AND isset($_SESSION['JournalDetail'])){

	unset($_SESSION['JournalDetail']->GLEntries);
	unset($_SESSION['JournalDetail']);

}

if (!isset($_SESSION['JournalDetail'])){
	$_SESSION['JournalDetail'] = new Journal;

	/* Make an array of the defined bank accounts - better to make it now than do it each time a line is added
	Journals cannot be entered against bank accounts GL postings involving bank accounts must be done using
	a receipt or a payment transaction to ensure a bank trans is available for matching off vs statements */

	$SQL = "SELECT accountcode FROM bankaccounts";
	$result = DB_query($SQL);
	$i=0;
	while ($Act = DB_fetch_row($result)){
		$_SESSION['JournalDetail']->BankAccounts[$i]= $Act[0];
		$i++;
	}

}

if (isset($_POST['JournalProcessDate'])){
	$_SESSION['JournalDetail']->JnlDate=$_POST['JournalProcessDate'];

	if (!Is_Date($_POST['JournalProcessDate'])){
		echo prnMsg(_('The date entered was not valid please enter the date to process the journal in the format'). $_SESSION['DefaultDateFormat'],'warn');
		$_POST['CommitBatch']='Do not do it the date is wrong';
	}
}
if (isset($_POST['JournalType'])){
	$_SESSION['JournalDetail']->JournalType = $_POST['JournalType'];
}

if (isset($_POST['CommitBatch']) AND $_POST['CommitBatch']==_('Accept and Process Journal')){

 /* once the GL analysis of the journal is entered
  process all the data in the session cookie into the DB
  A GL entry is created for each GL entry
*/

	$PeriodNo = GetPeriod($_SESSION['JournalDetail']->JnlDate);

     /*Start a transaction to do the whole lot inside */
	$result = DB_Txn_Begin();

	$TransNo = GetNextTransNo( 0 );

	foreach ($_SESSION['JournalDetail']->GLEntries as $JournalItem) {
		$SQL = "INSERT INTO gltrans (type,
									typeno,
									trandate,
									periodno,
									account,
									narrative,
									amount,
									tag)
				VALUES ('0',
					'" . $TransNo . "',
					'" . FormatDateForSQL($_SESSION['JournalDetail']->JnlDate) . "',
					'" . $PeriodNo . "',
					'" . $JournalItem->GLCode . "',
					'" . $JournalItem->Narrative  . "',
					'" . $JournalItem->Amount . "',
					'" . $JournalItem->tag."'
					)";
		$ErrMsg = _('Cannot insert a GL entry for the journal line because');
		$DbgMsg = _('The SQL that failed to insert the GL Trans record was');
		$result = DB_query($SQL,$ErrMsg,$DbgMsg,true);

		if ($_POST['JournalType']=='Reversing'){
			$SQL = "INSERT INTO gltrans (type,
										typeno,
										trandate,
										periodno,
										account,
										narrative,
										amount,
										tag)
					VALUES ('0',
						'" . $TransNo . "',
						'" . FormatDateForSQL($_SESSION['JournalDetail']->JnlDate) . "',
						'" . ($PeriodNo + 1) . "',
						'" . $JournalItem->GLCode . "',
						'" . _('Reversal') . " - " . $JournalItem->Narrative . "',
						'" . -($JournalItem->Amount) ."',
						'".$JournalItem->tag."'
						)";

			$ErrMsg =_('Cannot insert a GL entry for the reversing journal because');
			$DbgMsg = _('The SQL that failed to insert the GL Trans record was');
			$result = DB_query($SQL,$ErrMsg,$DbgMsg,true);
		}
	}


	$ErrMsg = _('Cannot commit the changes');
	$result= DB_Txn_Commit();

	echo prnMsg(_('Journal').' ' . $TransNo . ' '._('has been successfully entered'),'success');

	unset($_POST['JournalProcessDate']);
	unset($_POST['JournalType']);
	unset($_SESSION['JournalDetail']->GLEntries);
	unset($_SESSION['JournalDetail']);

	/*Set up a newy in case user wishes to enter another */
	echo '<br /><p align="center">
			<a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?NewJournal=Yes" class="btn btn-info">' . _('Enter Another GL Journal') . '</a></p>';
	/*And post the journal too */
	include ('includes/GLPostings.inc');
	include ('includes/footer.php');
	exit;

} elseif (isset($_GET['Delete'])){

	/* User hit delete the line from the journal */
	$_SESSION['JournalDetail']->Remove_GLEntry($_GET['Delete']);

} elseif (isset($_POST['Process']) AND $_POST['Process']==_('Submit')){ //user hit submit a new GL Analysis line into the journal
	if ($_POST['GLCode']!='') {
		$extract = explode(' - ',$_POST['GLCode']);
		$_POST['GLCode'] = $extract[0];
	}
	if ($_POST['Debit']>0) {
		$_POST['GLAmount'] = filter_number_format($_POST['Debit']);
	} elseif ($_POST['Credit']>0) {
		$_POST['GLAmount'] = -filter_number_format($_POST['Credit']);
	}
	if ($_POST['GLManualCode'] != ''){
		// If a manual code was entered need to check it exists and isnt a bank account
		$AllowThisPosting = true; //by default
		if ($_SESSION['ProhibitJournalsToControlAccounts'] == 1){
			if ($_SESSION['CompanyRecord']['gllink_debtors'] == '1' AND $_POST['GLManualCode'] == $_SESSION['CompanyRecord']['debtorsact']){
				echo prnMsg(_('GL Journals involving the debtors control account cannot be entered. The general ledger debtors ledger (AR) integration is enabled so control accounts are automatically maintained by nERP. This setting can be disabled in System Configuration'),'warn');
				$AllowThisPosting = false;
			}
			if ($_SESSION['CompanyRecord']['gllink_creditors'] == '1' AND $_POST['GLManualCode'] == $_SESSION['CompanyRecord']['creditorsact']){
				echo prnMsg(_('GL Journals involving the creditors control account cannot be entered. The general ledger creditors ledger (AP) integration is enabled so control accounts are automatically maintained by nERP. This setting can be disabled in System Configuration'),'warn');
				$AllowThisPosting = false;
			}
		}
		if (in_array($_POST['GLManualCode'], $_SESSION['JournalDetail']->BankAccounts)) {
			echo prnMsg(_('GL Journals involving a bank account cannot be entered') . '. ' . _('Bank account general ledger entries must be entered by either a bank account receipt or a bank account payment'),'info');
			$AllowThisPosting = false;
		}

		if ($AllowThisPosting) {
			$SQL = "SELECT accountname
				FROM chartmaster
				WHERE accountcode='" . $_POST['GLManualCode'] . "'";
			$Result=DB_query($SQL);

			if (DB_num_rows($Result)==0){
				echo prnMsg(_('The manual GL code entered does not exist in the system') . ' - ' . _('so this GL analysis item could not be added'),'warn');
				unset($_POST['GLManualCode']);
			} else {
				$myrow = DB_fetch_array($Result);
				$_SESSION['JournalDetail']->add_to_glanalysis($_POST['GLAmount'],
															$_POST['GLNarrative'],
															$_POST['GLManualCode'],
															$myrow['accountname'],
															$_POST['tag']);
			}
		}
	} else {
		$AllowThisPosting =true; //by default
		if ($_SESSION['ProhibitJournalsToControlAccounts'] == 1){
			if ($_SESSION['CompanyRecord']['gllink_debtors'] == '1'
				AND $_POST['GLCode'] == $_SESSION['CompanyRecord']['debtorsact']){

				echo prnMsg(_('GL Journals involving the debtors control account cannot be entered. The general ledger debtors ledger (AR) integration is enabled so control accounts are automatically maintained by nERP. This setting can be disabled in System Configuration'),'warn');
				$AllowThisPosting = false;
			}
			if ($_SESSION['CompanyRecord']['gllink_creditors'] == '1'
				AND $_POST['GLCode'] == $_SESSION['CompanyRecord']['creditorsact']){

				echo prnMsg(_('GL Journals involving the creditors control account cannot be entered. The general ledger creditors ledger (AP) integration is enabled so control accounts are automatically maintained by nERP. This setting can be disabled in System Configuration'),'warn');
				$AllowThisPosting = false;
			}
		}
		if ($_POST['GLCode'] == '' and $_POST['GLManualCode'] == '') {
			echo prnMsg(_('You must select a GL account code'),'info');
			$AllowThisPosting = false;
		}

		if (in_array($_POST['GLCode'], $_SESSION['JournalDetail']->BankAccounts)) {
			echo prnMsg(_('GL Journals involving a bank account cannot be entered') . '. ' . _('Bank account general ledger entries must be entered by either a bank account receipt or a bank account payment'),'warn');
			$AllowThisPosting = false;
		}

		if ($AllowThisPosting){
			if (!isset($_POST['GLAmount'])) {
				$_POST['GLAmount']=0;
			}
			$SQL = "SELECT accountname FROM chartmaster WHERE accountcode='" . $_POST['GLCode'] . "'";
			$Result=DB_query($SQL);
			$myrow=DB_fetch_array($Result);
			$_SESSION['JournalDetail']->add_to_glanalysis($_POST['GLAmount'],
															$_POST['GLNarrative'],
															$_POST['GLCode'],
															$myrow['accountname'],
															$_POST['tag']);
		}
	}

	/*Make sure the same receipt is not double processed by a page refresh */
	$Cancel = 1;
	unset($_POST['Credit']);
	unset($_POST['Debit']);
	unset($_POST['tag']);
	unset($_POST['GLManualCode']);
	unset($_POST['GLNarrative']);
}

if (isset($Cancel)){
	unset($_POST['Credit']);
	unset($_POST['Debit']);
	unset($_POST['GLAmount']);
	unset($_POST['GLCode']);
	unset($_POST['tag']);
	unset($_POST['GLManualCode']);
}

echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . ' ' . $Title.'
	</h1></a></div>';
echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" method="post" name="form">';

echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';



// A new table in the first column of the main table

if (!Is_Date($_SESSION['JournalDetail']->JnlDate)){
	// Default the date to the last day of the previous month
	$_SESSION['JournalDetail']->JnlDate = Date($_SESSION['DefaultDateFormat'],mktime(0,0,0,date('m'),0,date('Y')));
}

echo '<div class="row">
<div class="col-xs-4">
<div class="form-group has-error"> <label class="col-md-8 control-label">' . _('Date to Process Journal') . '</label>
							<input type="text" required="required" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="JournalProcessDate" maxlength="10" size="11" value="' . $_SESSION['JournalDetail']->JnlDate . '" /></div></div>
							<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Type') . '</label>
							<select name="JournalType" class="form-control">';

if ($_POST['JournalType'] == 'Reversing'){
	echo '<option selected="selected" value = "Reversing">' . _('Reversing') . '</option>';
	echo '<option value = "Normal">' . _('Normal') . '</option>';
} else {
	echo '<option value = "Reversing">' . _('Reversing') . '</option>';
	echo '<option selected="selected" value = "Normal">' . _('Normal') . '</option>';
}

echo '</select></div>
		</div>
	</div>';
/* close off the table in the first column  */


echo '<h3 class="page-header">' . _('Journal Line Entry') . '</h3>';
/* Set upthe form for the transaction entry for a GL Payment Analysis item */


/*now set up a GLCode field to select from avaialble GL accounts */

/* Set upthe form for the transaction entry for a GL Payment Analysis item */

//Select the tag
echo '<div class="row"><div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">GL Tag</label>
<select name="tag" class="form-control">';

$SQL = "SELECT tagref,
				tagdescription
		FROM tags
		ORDER BY tagref";

$result=DB_query($SQL);
echo '<option value="0">0 - ' . _('None') . '</option>';
while ($myrow=DB_fetch_array($result)){
	if (isset($_POST['tag']) AND $_POST['tag']==$myrow['tagref']){
		echo '<option selected="selected" value="' . $myrow['tagref'] . '">' . $myrow['tagref'].' - ' .$myrow['tagdescription'] . '</option>';
	} else {
		echo '<option value="' . $myrow['tagref'] . '">' . $myrow['tagref'].' - ' .$myrow['tagdescription'] . '</option>';
	}
}
echo '</select></div></div>';
// End select tag

if (!isset($_POST['GLManualCode'])) {
	$_POST['GLManualCode']='';
}
echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">GL Account Code</label>
<input type="text" autofocus="autofocus" name="GLManualCode" maxlength="12" size="12" class="form-control" onchange="inArray(this, GLCode.options,'.	"'".'The account code '."'".'+ this.value+ '."'".' doesnt exist'."'".')" value="'. $_POST['GLManualCode'] .'"  /></div></div>';

$sql="SELECT chartmaster.accountcode,
			chartmaster.accountname
		FROM chartmaster
			INNER JOIN glaccountusers ON glaccountusers.accountcode=chartmaster.accountcode AND glaccountusers.userid='" .  $_SESSION['UserID'] . "' AND glaccountusers.canupd=1
		ORDER BY chartmaster.accountcode";

$result=DB_query($sql);
echo '<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">Select GL Account</label>
	<select name="GLCode" class="form-control" onchange="return assignComboToInput(this,'.'GLManualCode'.')">
		<option value="">' . _('Select a general ledger account code') . '</option>';
while ($myrow=DB_fetch_array($result)){
	if (isset($_POST['GLCode']) AND $_POST['GLCode']==$myrow['accountcode']){
		echo '<option selected="selected" value="' . $myrow['accountcode'] . '">' . $myrow['accountcode'].' - ' .htmlspecialchars($myrow['accountname'], ENT_QUOTES,'UTF-8', false) . '</option>';
	} else {
		echo '<option value="' . $myrow['accountcode'] . '">' . $myrow['accountcode'].' - ' .htmlspecialchars($myrow['accountname'], ENT_QUOTES,'UTF-8', false)  . '</option>';
	}
}
echo '</select></div></div></div>';

if (!isset($_POST['GLNarrative'])) {
	$_POST['GLNarrative'] = '';
}
if (!isset($_POST['Credit'])) {
	$_POST['Credit'] = 0;
}
if (!isset($_POST['Debit'])) {
	$_POST['Debit'] = 0;
}

echo '<div class="row">
	<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Debit') . '</label>
		<input type="text" class="form-control" name="Debit" onchange="eitherOr(this,Credit)" maxlength="12" size="10" value="' . locale_number_format($_POST['Debit'],$_SESSION['CompanyRecord']['decimalplaces']) . '" /></div>
	</div>
	<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Credit') . '</label>
		<input type="text" class="form-control" name="Credit" onchange="eitherOr(this,Debit)" maxlength="12" size="10" value="' . locale_number_format($_POST['Credit'],$_SESSION['CompanyRecord']['decimalplaces']) . '" /></div>
	</div>
	<div class="col-xs-4">
<div class="form-group"> <label class="col-md-8 control-label">' . _('GL Narrative') . '</label>
		<input type="text" name="GLNarrative" class="form-control" maxlength="100" size="100" value="' . $_POST['GLNarrative'] . '" /></div>
	</div>
	</div>
	<br />'; /*Close the main table */
echo '<div class="row">
<div class="col-xs-4">
		<input type="submit" class="btn btn-success" name="Process" value="' . _('Submit') . '" />
	</div>
	</div>
	<br />';

echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="block">
<div class="block-title"><h3>' . _('Journal Summary') . '</h3></div>
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
		<thead>
		<tr>
			<th>' . _('GL Tag') . '</th>
			<th>' . _('GL Account') . '</th>
			<th>' . _('Debit') . '</th>
			<th>' . _('Credit') . '</th>
			<th>' . _('Narrative') . '</th>
			<th>' . _('Action') . '</th>
		</tr></thead>';

$DebitTotal=0;
$CreditTotal=0;

foreach ($_SESSION['JournalDetail']->GLEntries as $JournalItem) {
	$sql="SELECT tagdescription
			FROM tags
			WHERE tagref='".$JournalItem->tag . "'";
	$result=DB_query($sql);
	$myrow=DB_fetch_row($result);
	if ($JournalItem->tag==0) {
		$TagDescription=_('None');
	} else {
		$TagDescription=$myrow[0];
	}
	echo '<tr class="striped_row">
		<td>' . $JournalItem->tag . ' - ' . $TagDescription . '</td>
		<td>' . $JournalItem->GLCode . ' - ' . $JournalItem->GLActName . '</td>';
	if ($JournalItem->Amount>0) {
		echo '<td class="number">' . locale_number_format($JournalItem->Amount,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>
				<td></td>';
		$DebitTotal += $JournalItem->Amount;
	} elseif($JournalItem->Amount<0) {
		$Credit=(-1 * $JournalItem->Amount);
		echo '<td></td>
			<td class="number">' . locale_number_format($Credit,$_SESSION['CompanyRecord']['decimalplaces']) . '</td>';
		$CreditTotal=$CreditTotal+$Credit;
	}

	echo '<td>' . $JournalItem->Narrative  . '</td>
		<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?Delete=' . $JournalItem->ID . '" class="btn btn-danger">' . _('Delete') . '</a></td>
	</tr>';
}

echo '<tr class="striped_row"><td></td>
		<td class="number"><strong>' . _('Total') .  '</strong></td>
		<td class="number"><strong>' . locale_number_format($DebitTotal,$_SESSION['CompanyRecord']['decimalplaces']) . '</strong></td>
		<td class="number"><strong>' . locale_number_format($CreditTotal,$_SESSION['CompanyRecord']['decimalplaces']) . '</strong></td>
	</tr>';
if ($DebitTotal!=$CreditTotal) {
	echo '<tr><td><strong>' . _('Required to balance') .' - </strong>' .
		locale_number_format(abs($DebitTotal-$CreditTotal),$_SESSION['CompanyRecord']['decimalplaces']);
}
if ($DebitTotal>$CreditTotal) {
	echo ' ' . _('Credit') . '</td></tr>';
} else if ($DebitTotal<$CreditTotal) {
	echo ' ' . _('Debit') . '</td></tr>';
}
echo '</table>
    </div></div></div></div>';

if (abs($_SESSION['JournalDetail']->JournalTotal)<0.001 AND $_SESSION['JournalDetail']->GLItemCounter > 0){
	echo '<br />
			<br />
			<div class="row">
			<div class="col-xs-4">
				<input type="submit" class="btn btn-info" name="CommitBatch" value="' ._('Process Journal').'" />
			</div></div><br />';
} elseif(count($_SESSION['JournalDetail']->GLEntries)>0) {
	echo '
		<br />';
	echo prnMsg(_('The journal must balance ie debits equal to credits before it can be processed'),'warn');
}

echo '
	</form>';
include('includes/footer.php');
?>
