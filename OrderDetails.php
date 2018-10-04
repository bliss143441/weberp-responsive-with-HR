<?php


/* Session started in header.php for password checking and authorisation level check */
include('includes/session.php');

$_GET['OrderNumber']=(int)$_GET['OrderNumber'];

if (isset($_GET['OrderNumber'])) {
	$Title = _('Details for Sales Order Number:') . ' ' . $_GET['OrderNumber'];
} else {
	include('includes/header.php');
	echo '<br />';
	echo prnMsg(_('This page must be called with a sales order number to review') . '.<br />' . _('i.e.') . ' http://????/OrderDetails.php?OrderNumber=<i>xyz</i><br />' . _('Click on back') . '.','error');
	include('includes/footer.php');
	exit;
}

include('includes/header.php');

$OrderHeaderSQL = "SELECT salesorders.debtorno,
							debtorsmaster.name,
							salesorders.branchcode,
							salesorders.customerref,
							salesorders.comments,
							salesorders.orddate,
							salesorders.ordertype,
							salesorders.shipvia,
							salesorders.deliverto,
							salesorders.deladd1,
							salesorders.deladd2,
							salesorders.deladd3,
							salesorders.deladd4,
							salesorders.deladd5,
							salesorders.deladd6,
							salesorders.contactphone,
							salesorders.contactemail,
							salesorders.freightcost,
							salesorders.deliverydate,
							debtorsmaster.currcode,
							salesorders.fromstkloc,
							currencies.decimalplaces
					FROM salesorders INNER JOIN 	debtorsmaster
					ON salesorders.debtorno = debtorsmaster.debtorno
					INNER JOIN currencies
					ON debtorsmaster.currcode=currencies.currabrev
					WHERE salesorders.orderno = '" . $_GET['OrderNumber'] . "'";

$ErrMsg =  _('The order cannot be retrieved because');
$DbgMsg = _('The SQL that failed to get the order header was');
$GetOrdHdrResult = DB_query($OrderHeaderSQL, $ErrMsg, $DbgMsg);

if (DB_num_rows($GetOrdHdrResult)==1) {
	echo '<div class="block-header"><a href="" class="header-title-link"><h1> 
			' . ' ' . $Title . '
		</h1></a></div>
		<div class="col-xs-2"><a href="' . $RootPath . '/SelectCompletedOrder.php" class="btn btn-default">' . _('Back to Search') . '</a></div>
		<div class="col-xs-2"><a href="' . $RootPath . '/SelectCustomer.php" class="btn btn-info">' . _('Back to  Customers') . '</a></div><br /><br /><br />
';

	$myrow = DB_fetch_array($GetOrdHdrResult);
	$CurrDecimalPlaces = $myrow['decimalplaces'];

	if ($CustomerLogin ==1 AND $myrow['debtorno']!= $_SESSION['CustomerID']) {
		echo prnMsg (_('Your customer login will only allow you to view your own purchase orders'),'error');
		include('includes/footer.php');
		exit;
	}
	//retrieve invoice number
	$Invs = explode(' Inv ',$myrow['comments']);
	$Inv = '';
	foreach ($Invs as $value) {
		if (is_numeric($value)) {
			$Inv .= '<a href="' . $RootPath . '/PrintCustTransPortrait.php.php?FromTransNo=' . $value . '&InvOrCredit=Invoice" class="btn btn-info">'.$value.'</a>  ';
		}
	}

echo '
<div class="row gutter30">
        <div class="col-xs-12">
		<div class="block">
		                <div class="block-title">
                            <h2>' . _('Header Details For Order No:').' '.$_GET['OrderNumber'] . '</h2>
                        </div>
		<div class="table-responsive">
		';

	echo '<table id="general-table" class="table table-bordered">
			
			<tr>
				<th class="text">' . _('Customer Code') . ':</th>
				<td><a href="' . $RootPath . '/SelectCustomer.php?Select=' . $myrow['debtorno'] . '" class="btn btn-info">' . $myrow['debtorno'] . '</a></td>
				<th class="text">' . _('Customer Name') . ':</th>
				<th>' . $myrow['name'] . '</th>
			</tr>
			<tr>
				<th class="text">' . _('Customer Reference') . ':</th>
				<td>' . $myrow['customerref'] . '</td>
				<th class="text">' . _('Deliver To') . ':</th>
				<th>' . $myrow['deliverto'] . '</th>
			</tr>
			<tr>
				<th class="text">' . _('Ordered On') . ':</th>
				<td>' . ConvertSQLDate($myrow['orddate']) . '</td>
				<th class="text">' . _('Delivery Address 1') . ':</th>
				<td>' . $myrow['deladd1'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Requested Delivery') . ':</th>
				<td>' . ConvertSQLDate($myrow['deliverydate']) . '</td>
				<th class="text">' . _('Delivery Address 2') . ':</th>
				<td>' . $myrow['deladd2'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Order Currency') . ':</th>
				<td>' . $myrow['currcode'] . '</td>
				<th class="text">' . _('Delivery Address 3') . ':</th>
				<td>' . $myrow['deladd3'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Deliver From Location') . ':</th>
				<td>' . $myrow['fromstkloc'] . '</td>
				<th class="text">' . _('Delivery Address 4') . ':</th>
				<td>' . $myrow['deladd4'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Telephone') . ':</th>
				<td>' . $myrow['contactphone'] . '</td>
				<th class="text">' . _('Delivery Address 5') . ':</th>
				<td>' . $myrow['deladd5'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Email') . ':</th>
				<td><a href="mailto:' . $myrow['contactemail'] . '">' . $myrow['contactemail'] . '</a></td>
				<th class="text">' . _('Delivery Address 6') . ':</th>
				<td>' . $myrow['deladd6'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Freight Cost') . ':</th>
				<td>' . $myrow['freightcost'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Comments'). ': </th>
				<td colspan="3">' . $myrow['comments'] . '</td>
			</tr>
			<tr>
				<th class="text">' . _('Invoices') . ': </th>
				<td colspan="3">' . $Inv . '</td>
			</tr>
			</table>
			</div>
			</div>
			</div>
			</div>
			
			';
}

/*Now get the line items */

	$LineItemsSQL = "SELECT stkcode,
							stockmaster.description,
							stockmaster.volume,
							stockmaster.grossweight,
							stockmaster.decimalplaces,
							stockmaster.mbflag,
							stockmaster.units,
							stockmaster.discountcategory,
							stockmaster.controlled,
							stockmaster.serialised,
							unitprice,
							quantity,
							discountpercent,
							actualdispatchdate,
							qtyinvoiced,
							itemdue,
							poline,
							narrative
						FROM salesorderdetails INNER JOIN stockmaster
						ON salesorderdetails.stkcode = stockmaster.stockid
						WHERE orderno ='" . $_GET['OrderNumber'] . "'";

	$ErrMsg =  _('The line items of the order cannot be retrieved because');
	$DbgMsg =  _('The SQL used to retrieve the line items, that failed was');
	$LineItemsResult = DB_query($LineItemsSQL, $ErrMsg, $DbgMsg);

	if (DB_num_rows($LineItemsResult)>0) {

		$OrderTotal = 0;
		$OrderTotalVolume = 0;
		$OrderTotalWeight = 0;

		echo '
<div class="row gutter30">
<div class="col-xs-12">
		<div class="block">
		                <div class="block-title">
                            <h2>' . _('Line Details For Order No:').' '.$_GET['OrderNumber'] . '</h2>
                        </div>
		<div class="table-responsive">
			<table id="general-table" class="table table-bordered">
			<thead> 
			<tr>
				<th>' . _('PO Line') . '</th>
				<th>' . _('Stock ID') . '</th>
				<th>' . _('Description') . '</th>
				<th>' . _('Quantity') . '</th>
				<th>' . _('Unit') . '</th>
				<th>' . _('Price') . '</th>
				<th>' . _('Discount') . '</th>
				<th>' . _('Total') . '</th>
				<th>' . _('Delivered') . '</th>
				<th>' . _('Last Del') . '/' . _('Due Date') . '</th>
				<th>' . _('Narrative') . '</th>
			</tr></thead> ';

		while ($myrow=DB_fetch_array($LineItemsResult)) {

			if ($myrow['qtyinvoiced']>0){
				$DisplayActualDeliveryDate = ConvertSQLDate($myrow['actualdispatchdate']);
			} else {
		  		$DisplayActualDeliveryDate = '<span style="color:red;">' . ConvertSQLDate($myrow['itemdue']) . '</span>';
			}

			echo '<tr class="striped_row">
				<td>' . $myrow['poline'] . '</td>
				<td>' . $myrow['stkcode'] . '</td>
				<td>' . $myrow['description'] . '</td>
				<td class="number">' . $myrow['quantity'] . '</td>
				<td>' . $myrow['units'] . '</td>
				<td class="number">' . locale_number_format($myrow['unitprice'],$CurrDecimalPlaces) . '</td>
				<td class="number">' . locale_number_format(($myrow['discountpercent'] * 100),2) . '%' . '</td>
				<td class="number">' . locale_number_format($myrow['quantity'] * $myrow['unitprice'] * (1 - $myrow['discountpercent']),$CurrDecimalPlaces) . '</td>
				<td class="number">' . locale_number_format($myrow['qtyinvoiced'],$myrow['decimalplaces']) . '</td>
				<td>' . $DisplayActualDeliveryDate . '</td>
				<td>' . $myrow['narrative'] . '</td>
			</tr>';

			$OrderTotal += ($myrow['quantity'] * $myrow['unitprice'] * (1 - $myrow['discountpercent']));
			$OrderTotalVolume += ($myrow['quantity'] * $myrow['volume']);
			$OrderTotalWeight += ($myrow['quantity'] * $myrow['grossweight']);

		}
		$DisplayTotal = locale_number_format($OrderTotal,$CurrDecimalPlaces);
		$DisplayVolume = locale_number_format($OrderTotalVolume,2);
		$DisplayWeight = locale_number_format($OrderTotalWeight,2);

		echo '<tr>
				<td colspan="5" class="number"><strong>' . _('TOTAL Excl Tax/Freight') . '</strong></td>
				<td colspan="2" class="number">' . $DisplayTotal . '</td>
			</tr>
			</table></div></div></div></div>';

		echo '
			<div class="row gutter30">
<div class="col-xs-12">
		
		<div class="table-responsive">
		<table id="general-table" class="table table-bordered">
			<tr>
				<td><strong>' . _('Total Weight') . ':</strong></td>
				<td>' . $DisplayWeight . '</td>
				<td><strong>' . _('Total Volume') . ':</strong></td>
				<td>' . $DisplayVolume . '</td>
			</tr>
		</table></div></div></div>';
	}
//echo '</div>';
include('includes/footer.php');
?>
