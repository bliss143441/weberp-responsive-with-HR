<?php


include('includes/session.php');
include('includes/SQL_CommonFunctions.inc');

$InputError=0;

if (isset($_POST['FromDate']) AND !Is_Date($_POST['FromDate'])){
	$msg = _('The date from must be specified in the format') . ' ' . $_SESSION['DefaultDateFormat'];
	$InputError=1;
	unset($_POST['FromDate']);
}
if (isset($_POST['ToDate']) AND !Is_Date($_POST['ToDate'])){
	$msg = _('The date to must be specified in the format') . ' ' . $_SESSION['DefaultDateFormat'];
	$InputError=1;
	unset($_POST['ToDate']);
}

if (!isset($_POST['FromDate']) OR !isset($_POST['ToDate'])){

	$Title = _('Order Status Report');
	include ('includes/header.php');

	if ($InputError==1){
		echo  prnMsg($msg,'error');
	}

	echo '<div class="block-header"><a href="" class="header-title-link"><h1>' . _('Order Status Report') . '</h1></a></div>';
    echo '<div class="row gutter30">
		<div class="col-xs-12">';
	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
    echo '
		<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />
		<div class="row">
		<div class="col-xs-4">
        <div class="form-group has-error"> <label class="col-md-8 control-label">' . _('Date from which orders are to be listed') . '</label>
			<input type="text" required="required" autofocus="autofocus" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="FromDate" maxlength="10" size="11" value="' . Date($_SESSION['DefaultDateFormat'], Mktime(0,0,0,Date('m'),Date('d')-1,Date('y'))) . '" /></div>
		</div>
		<div class="col-xs-4">
        <div class="form-group has-error"> <label class="col-md-8 control-label">' . _('Date to which orders are to be listed') . '</label>
			<input type="text" required="required" class="form-control input-datepicker-close" data-date-format="dd/mm/yyyy" id="example-datepicker" name="ToDate" maxlength="10" size="11" value="' . Date($_SESSION['DefaultDateFormat']) . '" /></div>
		</div>
		<div class="col-xs-4">
        <div class="form-group has-error"> <label class="col-md-8 control-label">' . _('Inventory Category') . '</label>
			';

	$sql = "SELECT categorydescription, categoryid FROM stockcategory WHERE stocktype<>'D' AND stocktype<>'L'";
	$result = DB_query($sql);


	echo '<select required="required" name="CategoryID" class="form-control">
		<option selected="selected" value="All">' . _('Over All Categories') . '</option>';

	while ($myrow=DB_fetch_array($result)){
		echo '<option value="' . $myrow['categoryid'] . '">' . $myrow['categorydescription'] . '</option>';
	}
	echo '</select></div>
		</div>
		</div>
		<div class="row">
			<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Inventory Location') . '</label>
			<select name="Location" class="form-control">
				<option selected="selected" value="All">' . _('All Locations') . '</option>';

	$result= DB_query("SELECT locations.loccode, locationname FROM locations INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1");
	while ($myrow=DB_fetch_array($result)){
		echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
	}
	echo '</select></div></div>';

	echo '<div class="col-xs-4">
        <div class="form-group"> <label class="col-md-8 control-label">' . _('Back Order Only') . '</label>
			<select name="BackOrders" class="form-control">
				<option selected="selected" value="Yes">' . _('Only Show Back Orders') . '</option>
				<option value="No">' . _('Show All Orders') . '</option>
			</select></div>
		</div>
		
		
		<div class="col-xs-4">
        <div class="form-group"> <br />
			<input type="submit" class="btn btn-info" name="Go" value="' . _('Create PDF') . '" />
		</div>
		</div>
		</div>
	</form></div>
		</div>';

	include('includes/footer.php');
	exit;
} else {
	include('includes/PDFStarter.php');
	$pdf->addInfo('Title',_('Order Status Report'));
	$pdf->addInfo('Subject',_('Orders from') . ' ' . $_POST['FromDate'] . ' ' . _('to') . ' ' . $_POST['ToDate']);
	$line_height=12;
	$PageNumber = 1;
	$TotalDiffs = 0;
}


if ($_POST['CategoryID']=='All' AND $_POST['Location']=='All'){
	$sql= "SELECT salesorders.orderno,
				  salesorders.debtorno,
				  salesorders.branchcode,
				  salesorders.customerref,
				  salesorders.orddate,
				  salesorders.fromstkloc,
				  salesorders.printedpackingslip,
				  salesorders.datepackingslipprinted,
				  salesorderdetails.stkcode,
				  stockmaster.description,
				  stockmaster.units,
				  stockmaster.decimalplaces,
				  salesorderdetails.quantity,
				  salesorderdetails.qtyinvoiced,
				  salesorderdetails.completed,
				  debtorsmaster.name,
				  custbranch.brname,
				  locations.locationname
			 FROM salesorders
				 INNER JOIN salesorderdetails
				 ON salesorders.orderno = salesorderdetails.orderno
				 INNER JOIN stockmaster
				 ON salesorderdetails.stkcode = stockmaster.stockid
				 INNER JOIN debtorsmaster
				 ON salesorders.debtorno=debtorsmaster.debtorno
				 INNER JOIN custbranch
				 ON custbranch.debtorno=salesorders.debtorno
				 AND custbranch.branchcode=salesorders.branchcode
				 INNER JOIN locations
				 ON salesorders.fromstkloc=locations.loccode
				 INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
			 WHERE salesorders.orddate >='" . FormatDateForSQL($_POST['FromDate']) . "'
				  AND salesorders.orddate <='" . FormatDateForSQL($_POST['ToDate']) . "'
			 AND salesorders.quotation=0";

} elseif ($_POST['CategoryID']!='All' AND $_POST['Location']=='All') {
	$sql= "SELECT salesorders.orderno,
				  salesorders.debtorno,
				  salesorders.branchcode,
				  salesorders.customerref,
				  salesorders.orddate,
				  salesorders.fromstkloc,
				  salesorders.printedpackingslip,
				  salesorders.datepackingslipprinted,
				  salesorderdetails.stkcode,
				  stockmaster.description,
				  stockmaster.units,
				  stockmaster.decimalplaces,
				  salesorderdetails.quantity,
				  salesorderdetails.qtyinvoiced,
				  salesorderdetails.completed,
				  debtorsmaster.name,
				  custbranch.brname,
				  locations.locationname
			 FROM salesorders
				 INNER JOIN salesorderdetails
				 ON salesorders.orderno = salesorderdetails.orderno
				 INNER JOIN stockmaster
				 ON salesorderdetails.stkcode = stockmaster.stockid
				 INNER JOIN debtorsmaster
				 ON salesorders.debtorno=debtorsmaster.debtorno
				 INNER JOIN custbranch
				 ON custbranch.debtorno=salesorders.debtorno
				 AND custbranch.branchcode=salesorders.branchcode
				 INNER JOIN locations
				 ON salesorders.fromstkloc=locations.loccode
				 INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
			 WHERE stockmaster.categoryid ='" . $_POST['CategoryID'] . "'
				  AND orddate >='" . FormatDateForSQL($_POST['FromDate']) . "'
				  AND orddate <='" . FormatDateForSQL($_POST['ToDate']) . "'
			 AND salesorders.quotation=0";


} elseif ($_POST['CategoryID']=='All' AND $_POST['Location']!='All') {
	$sql= "SELECT salesorders.orderno,
				  salesorders.debtorno,
				  salesorders.branchcode,
				  salesorders.customerref,
				  salesorders.orddate,
				  salesorders.fromstkloc,
				  salesorders.printedpackingslip,
				  salesorders.datepackingslipprinted,
				  salesorderdetails.stkcode,
				  stockmaster.description,
				  stockmaster.units,
				  stockmaster.decimalplaces,
				  salesorderdetails.quantity,
				  salesorderdetails.qtyinvoiced,
				  salesorderdetails.completed,
				  debtorsmaster.name,
				  custbranch.brname,
				  locations.locationname
			 FROM salesorders
				 INNER JOIN salesorderdetails
				 ON salesorders.orderno = salesorderdetails.orderno
				 INNER JOIN stockmaster
				 ON salesorderdetails.stkcode = stockmaster.stockid
				 INNER JOIN debtorsmaster
				 ON salesorders.debtorno=debtorsmaster.debtorno
				 INNER JOIN custbranch
				 ON custbranch.debtorno=salesorders.debtorno
				 AND custbranch.branchcode=salesorders.branchcode
				 INNER JOIN locations
				 ON salesorders.fromstkloc=locations.loccode
				 INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
			 WHERE salesorders.fromstkloc ='" . $_POST['Location'] . "'
				  AND salesorders.orddate >='" . FormatDateForSQL($_POST['FromDate']) . "'
				  AND salesorders.orddate <='" . FormatDateForSQL($_POST['ToDate']) . "'
			 AND salesorders.quotation=0";


} elseif ($_POST['CategoryID']!='All' AND $_POST['location']!='All'){

	$sql= "SELECT salesorders.orderno,
				  salesorders.debtorno,
				  salesorders.branchcode,
				  salesorders.customerref,
				  salesorders.orddate,
				  salesorders.fromstkloc,
				  salesorders.printedpackingslip,
				  salesorders.datepackingslipprinted,
				  salesorderdetails.stkcode,
				  stockmaster.description,
				  stockmaster.units,
				  stockmaster.decimalplaces,
				  salesorderdetails.quantity,
				  salesorderdetails.qtyinvoiced,
				  salesorderdetails.completed,
				  debtorsmaster.name,
				  custbranch.brname,
				  locations.locationname
			 FROM salesorders
				 INNER JOIN salesorderdetails
				 ON salesorders.orderno = salesorderdetails.orderno
				 INNER JOIN stockmaster
				 ON salesorderdetails.stkcode = stockmaster.stockid
				 INNER JOIN debtorsmaster
				 ON salesorders.debtorno=debtorsmaster.debtorno
				 INNER JOIN custbranch
				 ON custbranch.debtorno=salesorders.debtorno
				 AND custbranch.branchcode=salesorders.branchcode
				 INNER JOIN locations
				 ON salesorders.fromstkloc=locations.loccode
				 INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canview=1
			 WHERE stockmaster.categoryid ='" . $_POST['CategoryID'] . "'
				  AND salesorders.fromstkloc ='" . $_POST['Location'] . "'
				  AND salesorders.orddate >='" . FormatDateForSQL($_POST['FromDate']) . "'
				  AND salesorders.orddate <='" . FormatDateForSQL($_POST['ToDate']) . "'
			 AND salesorders.quotation=0";
}

if ($_POST['BackOrders']=='Yes'){
		$sql .= " AND salesorderdetails.quantity-salesorderdetails.qtyinvoiced >0";
}
//Add salesman role control
if ($_SESSION['SalesmanLogin'] != '') {
		$sql .= " AND salesorders.salesperson='" . $_SESSION['SalesmanLogin'] . "'";
}

$sql .= " ORDER BY salesorders.orderno";

$Result=DB_query($sql,'','',false,false); //dont trap errors here

if (DB_error_no()!=0){
	include('includes/header.php');
	echo '<br /><p class="text-danger">' . _('An error occurred getting the orders details') .'</p>';
	if ($debug==1){
		echo '<br />' . _('The SQL used to get the orders that failed was') . '<br />' . $sql;
	}
	include ('includes/footer.php');
	exit;
} elseif (DB_num_rows($Result)==0){
	$Title=_('Order Status Report - No Data');
  	include('includes/header.php');
	echo   prnMsg(_('There were no orders found in the database within the period from') . ' ' . $_POST['FromDate'] . ' ' . _('to') . ' '. $_POST['ToDate'] . '. ' . _('Please try again selecting a different date range'),'info');
	include('includes/footer.php');
	exit;
}

include ('includes/PDFOrderStatusPageHeader.inc');

$OrderNo =0; /*initialise */

while ($myrow=DB_fetch_array($Result)){

	$pdf->line($XPos, $YPos,$Page_Width-$Right_Margin, $YPos);

	$YPos -= $line_height;
	/*Set up headings */
	/*draw a line */
$pdf->SetFont('arsenalb', '', 11);
	if ($myrow['orderno']!=$OrderNo	){
		$LeftOvers = $pdf->addTextWrap($Left_Margin+2,$YPos,40,$FontSize,_('Order'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+40,$YPos,150,$FontSize,_('Customer'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+190,$YPos,110,$FontSize,_('Branch'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+300,$YPos,60,$FontSize,_('Ord Date'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+360,$YPos,60,$FontSize,_('Location'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+420,$YPos,80,$FontSize,_('Status'), 'left');

		$YPos-=$line_height;

		/*draw a line */
		$pdf->line($XPos, $YPos,$Page_Width-$Right_Margin, $YPos);
		$pdf->line($XPos, $YPos-$line_height*2,$XPos, $YPos+$line_height*2);
		$pdf->line($Page_Width-$Right_Margin, $YPos-$line_height*2,$Page_Width-$Right_Margin, $YPos+$line_height*2);


		if ($YPos - (2 *$line_height) < $Bottom_Margin){
			/*Then set up a new page */
			$PageNumber++;
			include ('includes/PDFOrderStatusPageHeader.inc');
			$OrderNo=0;
		} /*end of new page header  */
		$YPos -= $line_height;
$pdf->SetFont('arsenal', '', 10);
$FontSize=8;
		$LeftOvers = $pdf->addTextWrap($Left_Margin+2,$YPos,40,$FontSize,$myrow['orderno'], 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+40,$YPos,150,$FontSize,html_entity_decode($myrow['name'],ENT_QUOTES,'UTF-8'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+190,$YPos,110,$FontSize,$myrow['brname'], 'left');

		$LeftOvers = $pdf->addTextWrap($Left_Margin+300,$YPos,60,$FontSize,ConvertSQLDate($myrow['orddate']), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+360,$YPos,80,$FontSize,$myrow['locationname'], 'left');

		if ($myrow['printedpackingslip']==1){
			$PackingSlipPrinted = _('Printed') . ' ' . ConvertSQLDate($myrow['datepackingslipprinted']);
		} else {
			$PackingSlipPrinted =_('Not yet printed');
		}

		$LeftOvers = $pdf->addTextWrap($Left_Margin+420,$YPos,100,$FontSize,$PackingSlipPrinted, 'left');
		$YPos -= $line_height;
		$pdf->line($XPos, $YPos,$Page_Width-$Right_Margin, $YPos);

		$YPos -= ($line_height);
$pdf->SetFont('arsenalb', '', 10);
//$FontSize=10;
		 /*Its not the first line */
		$OrderNo = $myrow['orderno'];
		$LeftOvers = $pdf->addTextWrap($Left_Margin,$YPos,60,$FontSize,_('Code'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+60,$YPos,120,$FontSize,_('Description'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+180,$YPos,60,$FontSize,_('Ordered'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+240,$YPos,60,$FontSize,_('Invoiced'), 'left');
		$LeftOvers = $pdf->addTextWrap($Left_Margin+320,$YPos,60,$FontSize,_('Outstanding'), 'left');
		$YPos -= ($line_height);

	}
$pdf->SetFont('arsenal', '', 9);
	$LeftOvers = $pdf->addTextWrap($Left_Margin,$YPos,60,$FontSize,$myrow['stkcode'], 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+60,$YPos,120,$FontSize,$myrow['description'], 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+180,$YPos,60,$FontSize,locale_number_format($myrow['quantity'],$myrow['decimalplaces']), 'left');
	$LeftOvers = $pdf->addTextWrap($Left_Margin+240,$YPos,60,$FontSize,locale_number_format($myrow['qtyinvoiced'],$myrow['decimalplaces']), 'left');

	  if ($myrow['quantity']>$myrow['qtyinvoiced']){
		   $LeftOvers = $pdf->addTextWrap($Left_Margin+320,$YPos,60,$FontSize,locale_number_format($myrow['quantity']-$myrow['qtyinvoiced'],$myrow['decimalplaces']), 'left');
	  } else {
		   $LeftOvers = $pdf->addTextWrap($Left_Margin+320,$YPos,60,$FontSize,_('Complete'), 'left');
	  }

	 $YPos -= ($line_height);
	 if ($YPos - (2 *$line_height) < $Bottom_Margin){
		/*Then set up a new page */
		$PageNumber++;
		include ('includes/PDFOrderStatusPageHeader.inc');
		$OrderNo=0;
	 } /*end of new page header  */
} /* end of while there are delivery differences to print */
$pdf->OutputD('nERP' . '_OrderStatus_' . date('Y-m-d') . '.pdf');
$pdf->__destruct();
?>
