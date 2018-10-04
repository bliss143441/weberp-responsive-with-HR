<?php


include('includes/SQL_CommonFunctions.inc');
include ('includes/session.php');

$InputError=0;
if (isset($_POST['Date']) AND !Is_Date($_POST['Date'])){
	$msg = _('The date must be specified in the format') . ' ' . $_SESSION['DefaultDateFormat'];
	$InputError=1;
	unset($_POST['Date']);
}

if (!isset($_POST['Date'])){

	 $Title = _('Supplier Transaction Listing');
	 include ('includes/header.php');

	echo '<div class="block-header"><a href="" class="header-title-link"><h1> ' . ' '
		. _('Supplier Transaction Listing') . '</h1></a></div>';

	if ($InputError==1){
		echo prnMsg($msg,'error');
	}
	echo '<div class="row gutter30">
<div class="col-xs-12">';
	 echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
     
	 echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	 echo '<div class="row">
<div class="col-xs-5">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Date for which the transactions to be listed') . '</label>
			<input type="text" name="Date" maxlength="10" size="11" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" value="' . Date($_SESSION['DefaultDateFormat']) . '" /></div>
			</div>';

	echo '
<div class="col-xs-3">
<div class="form-group"> <label class="col-md-8 control-label">' . _('Transaction type') . '</label>
			<select name="TransType" class="form-control">
				<option value="20">' . _('Invoices') . '</option>
				<option value="21">' . _('Credit Notes') . '</option>
				<option value="22">' . _('Payments') . '</option>
				</select></div>
		</div>';

	 echo '
			
			
<div class="col-xs-4">
<div class="form-group"> <br />
				<input type="submit" class="btn btn-warning" name="Go" value="' . _('Create PDF') . '" />
			</div>';
     echo '</div>
	 </div>
           </form>
		   </div>
		   </div>
		   ';

	 include('includes/footer.php');
	 exit;
} else {

	include('includes/ConnectDB.inc');
}

$sql= "SELECT type,
			supplierno,
			suppreference,
			trandate,
			ovamount,
			ovgst,
			transtext,
			currcode,
			decimalplaces AS currdecimalplaces,
			suppname
		FROM supptrans INNER JOIN suppliers
		ON supptrans.supplierno = suppliers.supplierid
		INNER JOIN currencies
		ON suppliers.currcode=currencies.currabrev
		WHERE type='" . $_POST['TransType'] . "'
		AND trandate='" . FormatDateForSQL($_POST['Date']) . "'";

$result=DB_query($sql,'','',false,false);

if (DB_error_no()!=0){
	$Title = _('Payment Listing');
	include('includes/header.php');
	echo prnMsg(_('An error occurred getting the payments'),'error');
	if ($debug==1){
			prnMsg(_('The SQL used to get the receipt header information that failed was') . ':<br />' . $sql,'error');
	}
	include('includes/footer.php');
  	exit;
} elseif (DB_num_rows($result) == 0){
	$Title = _('Payment Listing');
	include('includes/header.php');
	echo '<br />';
  	echo prnMsg (_('There were no transactions found in the database for the date') . ' ' . $_POST['Date'] .'. '._('Please try again selecting a different date'), 'info');
	include('includes/footer.php');
  	exit;
}

include('includes/PDFStarter.php');

/*PDFStarter.php has all the variables for page size and width set up depending on the users default preferences for paper size */

$pdf->addInfo('Title',_('Supplier Transaction Listing'));
$pdf->addInfo('Subject',_('Supplier transaction listing from') . '  ' . $_POST['Date'] );
$line_height=12;
$PageNumber = 1;
$TotalCheques = 0;

include ('includes/PDFSuppTransListingPageHeader.inc');

while ($myrow=DB_fetch_array($result)){
    $CurrDecimalPlaces = $myrow['currdecimalplaces'];
	$LeftOvers = $pdf->addTextWrap($Left_Margin,$YPos,160,$FontSize,$myrow['suppname'], 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+162,$YPos,80,$FontSize,$myrow['suppreference'], 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+242,$YPos,70,$FontSize,ConvertSQLDate($myrow['trandate']), 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+312,$YPos,70,$FontSize,locale_number_format($myrow['ovamount'],$CurrDecimalPlaces), 'right');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+382,$YPos,70,$FontSize,locale_number_format($myrow['ovgst'],$CurrDecimalPlaces), 'right');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+452,$YPos,70,$FontSize,locale_number_format($myrow['ovamount']+$myrow['ovgst'],$CurrDecimalPlaces), 'right');

	  $YPos -= ($line_height);
	  $TotalCheques = $TotalCheques - $myrow['ovamount'];

	  if ($YPos - (2 *$line_height) < $Bottom_Margin){
		/*Then set up a new page */
		$PageNumber++;
		include ('includes/PDFChequeListingPageHeader.inc');
	  } /*end of new page header  */
} /* end of while there are customer receipts in the batch to print */


$YPos-=$line_height;
$LeftOvers = $pdf->addTextWrap($Left_Margin+452,$YPos,70,$FontSize,locale_number_format(-$TotalCheques,$CurrDecimalPlaces), 'right');
$LeftOvers = $pdf->addTextWrap($Left_Margin+265,$YPos,300,$FontSize,_('Total') . '  ' . _('Transactions'), 'left');

$ReportFileName = 'nERP' . '_SuppTransListing_' . date('Y-m-d').'.pdf';
$pdf->OutputD($ReportFileName);
$pdf->__destruct();
?>