<?php

include('includes/DefineSuppAllocsClass.php');

include('includes/session.php');
$Title = _('Supplier Payment') . '/' . _('Credit Note Allocations');
$ViewTopic = 'ARTransactions';// Filename in ManualContents.php's TOC./* RChacon: To do ManualAPInquiries.html from ManualARInquiries.html */
$BookMark = 'SupplierAllocations';
include('includes/header.php');

echo '<div class="block-header"><a href="" class="header-title-link"><h1> ', // Icon title.
	_('Supplier Allocations'), '</h1></a></div>';// Page title.

include('includes/SQL_CommonFunctions.inc');

if (isset($_POST['UpdateDatabase']) OR isset($_POST['RefreshAllocTotal'])) {

	if (!isset($_SESSION['Alloc'])){
		echo prnMsg(
			_('Allocations can not be processed again') . '. ' .
				_('If you hit refresh on this page after having just processed an allocation') . ', ' .
				_('try to use the navigation links provided rather than the back button, to avoid this message in future'),
			'warn');
		include('includes/footer.php');
		exit;
	}

/*1st off run through and update the array with the amounts allocated
	This works because the form has an input field called the value of
	AllocnItm->ID for each record of the array - and PHP sets the value of
	the form variable on a post*/

	$InputError = 0;
	$TotalAllocated = 0;
	$TotalDiffOnExch = 0;

	for ($AllocCounter=0; $AllocCounter < $_POST['TotalNumberOfAllocs']; $AllocCounter++){

		$_POST['Amt' . $AllocCounter] = filter_number_format($_POST['Amt' . $AllocCounter]);

		if (!is_numeric($_POST['Amt' . $AllocCounter])){
		      $_POST['Amt' . $AllocCounter] = 0;
		 }
		 if ($_POST['Amt' . $AllocCounter] < 0){
			echo prnMsg(_('The entry for the amount to allocate was negative') . '. ' . _('A positive allocation amount is expected'),'error');
			$_POST['Amt' . $AllocCounter] = 0;
		 }

		if (isset($_POST['All' . $AllocCounter]) AND $_POST['All' . $AllocCounter] == True){
			/* $_POST['YetToAlloc...] is a hidden item on the form not locale_number_formatted */
			$_POST['Amt' . $AllocCounter] = $_POST['YetToAlloc' . $AllocCounter];

		 }

		  /*Now check to see that the AllocAmt is no greater than the
		 amount left to be allocated against the transaction under review */

		 if ($_POST['Amt' . $AllocCounter] > $_POST['YetToAlloc' . $AllocCounter]){
		     $_POST['Amt' . $AllocCounter] = $_POST['YetToAlloc' . $AllocCounter];
		 }

		 $_SESSION['Alloc']->Allocs[$_POST['AllocID' . $AllocCounter]]->AllocAmt = $_POST['Amt' . $AllocCounter];

		 /*recalcuate the new difference on exchange
		 (a +positive amount is a gain -ve a loss)*/

		 $_SESSION['Alloc']->Allocs[$_POST['AllocID' . $AllocCounter]]->DiffOnExch = ($_POST['Amt' . $AllocCounter] / $_SESSION['Alloc']->TransExRate) - ($_POST['Amt' . $AllocCounter] / $_SESSION['Alloc']->Allocs[$_POST['AllocID' . $AllocCounter]]->ExRate);

		 $TotalDiffOnExch += $_SESSION['Alloc']->Allocs[$_POST['AllocID' . $AllocCounter]]->DiffOnExch;
		 $TotalAllocated += round($_POST['Amt' . $AllocCounter],$_SESSION['Alloc']->CurrDecimalPlaces);
	} /*end of the loop to set the new allocation amounts,
	recalc diff on exchange and add up total allocations */

	if ($TotalAllocated + $_SESSION['Alloc']->TransAmt > 0.005){
		echo '<br />';
		echo prnMsg(_('These allocations cannot be processed because the amount allocated is more than the amount of the') . ' ' . $_SESSION['Alloc']->TransTypeName  . ' ' . _('being allocated') . '<br />' . _('Total allocated') . ' = ' . locale_number_format($TotalAllocated,$_SESSION['Alloc']->CurrDecimalPlaces) . ' ' . _('and the total amount of the Credit/payment was') . ' ' . locale_number_format(-$_SESSION['Alloc']->TransAmt,$_SESSION['Alloc']->CurrDecimalPlaces) ,'error');
		echo '<br />';
		$InputError = 1;
	}

}

if (isset($_POST['UpdateDatabase'])){

	if ($InputError == 0){ /* ie all the traps were passed */

	/* actions to take having checked that the input is sensible
	1st set up a transaction on this thread*/

		DB_Txn_Begin();

		foreach ($_SESSION['Alloc']->Allocs as $AllocnItem) {

			  if ($AllocnItem->OrigAlloc >0 AND ($AllocnItem->OrigAlloc != $AllocnItem->AllocAmt)){

			  /*Orignial allocation was not 0 and it has now changed
			    need to delete the old allocation record */

				$SQL = "DELETE FROM suppallocs WHERE id = '" . $AllocnItem->PrevAllocRecordID . "'";

				$ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' . _('The existing allocation for') . ' ' . $AllocnItem->TransType .' ' . $AllocnItem->TypeNo . ' ' . _('could not be deleted because');
				$DbgMsg = _('The following SQL to delete the allocation record was used');

				$Result=DB_query($SQL, $ErrMsg, $DbgMsg, True);
			 }

			 if ($AllocnItem->OrigAlloc != $AllocnItem->AllocAmt){

			 /*Only when there has been a change to the allocated amount
			 do we need to insert a new allocation record and update
			 the transaction with the new alloc amount and diff on exch */

				     if ($AllocnItem->AllocAmt > 0){
					     $SQL = "INSERT INTO suppallocs (datealloc,
														amt,
														transid_allocfrom,
														transid_allocto)
										VALUES ('" . FormatDateForSQL(date($_SESSION['DefaultDateFormat'])) . "',
										     		'" . $AllocnItem->AllocAmt . "',
												'" . $_SESSION['Alloc']->AllocTrans . "',
												'" . $AllocnItem->ID . "')";

						  $ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' .  _('The supplier allocation record for') . ' ' . $AllocnItem->TransType . ' ' .  $AllocnItem->TypeNo . ' ' ._('could not be inserted because');
						  $DbgMsg = _('The following SQL to insert the allocation record was used');

					     $Result=DB_query($SQL, $ErrMsg, $DbgMsg, True);
				     }
				     $NewAllocTotal = $AllocnItem->PrevAlloc + $AllocnItem->AllocAmt;

				     if (abs($NewAllocTotal-$AllocnItem->TransAmount) < 0.01){
					     $Settled = 1;
				     } else {
					     $Settled = 0;
				     }

				     $SQL = "UPDATE supptrans SET diffonexch='" . $AllocnItem->DiffOnExch . "',
												alloc = '" .  $NewAllocTotal . "',
												settled = '" . $Settled . "'
							WHERE id = '" . $AllocnItem->ID . "'";

					  $ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' . _('The debtor transaction record could not be modified for the allocation against it because');

					  $DbgMsg = _('The following SQL to update the debtor transaction record was used');

				     $Result = DB_query($SQL, $ErrMsg, $DbgMsg, True);

			 } /*end if the new allocation is different to what it was before */

		}  /*end of the loop through the array of allocations made */

		/*Now update the payment or credit note with the amount allocated
		and the new diff on exchange */

		if (abs($TotalAllocated + $_SESSION['Alloc']->TransAmt) < 0.01){
		   $Settled = 1;
		} else {
		   $Settled = 0;
		}

		$SQL = "UPDATE supptrans SET alloc = '" .  -$TotalAllocated . "',
					diffonexch = '" . -$TotalDiffOnExch . "',
					settled='" . $Settled . "'
				WHERE id = '" . $_SESSION['AllocTrans'] . "'";

		$ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' .
					 _('The supplier payment or credit note transaction could not be modified for the new allocation and exchange difference because');

		$DbgMsg = _('The following SQL to update the payment or credit note was used');

		$Result = DB_query($SQL, $ErrMsg, $DbgMsg, True);

		/*Almost there ... if there is a change in the total diff on exchange
		 and if the GLLink to debtors is active - need to post diff on exchange to GL */

		$MovtInDiffOnExch = $_SESSION['Alloc']->PrevDiffOnExch + $TotalDiffOnExch;
		if ($MovtInDiffOnExch !=0 ){

		   if ($_SESSION['CompanyRecord']['gllink_debtors'] == 1){

		      $PeriodNo = GetPeriod($_SESSION['Alloc']->TransDate);

		      $_SESSION['Alloc']->TransDate = FormatDateForSQL($_SESSION['Alloc']->TransDate);

		      $SQL = "INSERT INTO gltrans (type,
							typeno,
							trandate,
							periodno,
							account,
							narrative,
							amount)
						VALUES ('" . $_SESSION['Alloc']->TransType . "',
							'" . $_SESSION['Alloc']->TransNo . "',
							'" . $_SESSION['Alloc']->TransDate . "',
							'" . $PeriodNo . "',
							'" . $_SESSION['CompanyRecord']['purchasesexchangediffact'] . "',
							'". _('Exchange difference') . "',
							'" . $MovtInDiffOnExch . "')";

		      $ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ': ' . _('The GL entry for the difference on exchange arising out of this allocation could not be inserted because');
		      $DbgMsg = _('The following SQL to insert the GLTrans record was used');

		      $Result = DB_query($SQL, $ErrMsg, $DbgMsg, True);


		      $SQL = "INSERT INTO gltrans (type,
							typeno,
							trandate,
							periodno,
							account,
							narrative,
							amount)
						VALUES ('" . $_SESSION['Alloc']->TransType . "',
							'" . $_SESSION['Alloc']->TransNo . "',
							'" . $_SESSION['Alloc']->TransDate . "',
							'" . $PeriodNo . "',
							'" . $_SESSION['CompanyRecord']['creditorsact'] . "',
							'" . _('Exchange difference') . "',
							'" . -$MovtInDiffOnExch . "')";

		      $ErrMsg = _('CRITICAL ERROR') . '! ' . _('NOTE DOWN THIS ERROR AND SEEK ASSISTANCE') . ' : ' .
		      			 _('The GL entry for the difference on exchange arising out of this allocation could not be inserted because');

		      $DbgMsg = _('The following SQL to insert the GLTrans record was used');

		      $Result = DB_query($SQL, $ErrMsg, $DbgMsg, True);

		   }

		}

	 /* OK Commit the transaction */

		DB_Txn_Commit();

	/*finally delete the session variables holding all the previous data */

		unset($_SESSION['AllocTrans']);
		unset($_SESSION['Alloc']);
		unset($_POST['AllocTrans']);

	} /* end of processing required if there were no input errors trapped */
}

/*The main logic determines whether the page is called with a Supplier code
a specific transaction or with no parameters ie else
If with a supplier code show just that supplier's payments and credits for allocating
If with a specific payment or credit show the invoices and credits available
for allocating to  */

echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '" method="post">';

echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

if (isset($_POST['SupplierID'])){
 	$_GET['SupplierID'] = $_POST['SupplierID'];
	echo '<input type="hidden" name="SupplierID" value="' . $_POST['SupplierID'] . '" />';
}

If (isset($_GET['AllocTrans'])){

	/*page called with a specific transaction ID for allocating
	SupplierID may also be set but this is the logic to follow
	the SupplierID logic is only for showing the payments and credits to allocate*/


	/*The logic is:
	- read in the transaction into a session class variable
	- read in the invoices available for allocating to into a session array of allocs object
	- Display the supplier name the transaction being allocated amount and trans no
	- Display the invoices for allocating to with a form entry for each one
	for the allocated amount to be entered */


	$_SESSION['Alloc'] = new Allocation;

	/*The session varibale AllocTrans is set from the passed variable AllocTrans
	on the first pass */

	$_SESSION['AllocTrans'] = $_GET['AllocTrans'];
	$_POST['AllocTrans'] = $_GET['AllocTrans'];


	$SQL= "SELECT systypes.typename,
				supptrans.type,
				supptrans.transno,
				supptrans.trandate,
				supptrans.supplierno,
				suppliers.suppname,
				supptrans.rate,
				(supptrans.ovamount+supptrans.ovgst) AS total,
				supptrans.diffonexch,
				supptrans.alloc,
				currencies.decimalplaces
		    FROM supptrans INNER JOIN systypes
			ON supptrans.type = systypes.typeid
			INNER JOIN suppliers
			ON supptrans.supplierno = suppliers.supplierid
			INNER JOIN currencies
			ON suppliers.currcode=currencies.currabrev
		    WHERE supptrans.id='" . $_SESSION['AllocTrans'] . "'";

	$Result = DB_query($SQL);
	if (DB_num_rows($Result) != 1){
		echo prnMsg(_('There was a problem retrieving the information relating the transaction selected') . '. ' . _('Allocations are unable to proceed'), 'error');
		if ($debug == 1){
			echo '<br />' . _('The SQL that was used to retrieve the transaction information was') . ' :<br />'  . $SQL;
		}
		exit;
	}

	$myrow = DB_fetch_array($Result);

	$_SESSION['Alloc']->AllocTrans = $_SESSION['AllocTrans'];
	$_SESSION['Alloc']->SupplierID = $myrow['supplierno'];
	$_SESSION['Alloc']->SuppName = $myrow['suppname'];;
	$_SESSION['Alloc']->TransType = $myrow['type'];
	$_SESSION['Alloc']->TransTypeName = _($myrow['typename']);
	$_SESSION['Alloc']->TransNo = $myrow['transno'];
	$_SESSION['Alloc']->TransExRate = $myrow['rate'];
	$_SESSION['Alloc']->TransAmt = $myrow['total'];
	$_SESSION['Alloc']->PrevDiffOnExch = $myrow['diffonexch'];
	$_SESSION['Alloc']->TransDate = ConvertSQLDate($myrow['trandate']);
	$_SESSION['Alloc']->CurrDecimalPlaces = $myrow['decimalplaces'];

	/* Now populate the array of possible (and previous actual) allocations for this supplier */
	/*First get the transactions that have outstanding balances ie Total-Alloc >0 */

	$SQL= "SELECT supptrans.id,
				typename,
				transno,
				trandate,
				suppreference,
				rate,
				ovamount+ovgst AS total,
				diffonexch,
				alloc
			FROM supptrans INNER JOIN systypes
			ON supptrans.type = systypes.typeid
			WHERE supptrans.settled=0
			AND abs(ovamount+ovgst-alloc)>0.009
			AND supplierno='" . $_SESSION['Alloc']->SupplierID . "'";

	$ErrMsg = _('There was a problem retrieving the transactions available to allocate to');

	$DbgMsg = _('The SQL that was used to retrieve the transaction information was');

	$Result = DB_query($SQL, $ErrMsg, $DbgMsg);

	while ($myrow=DB_fetch_array($Result)){
		$_SESSION['Alloc']->add_to_AllocsAllocn ($myrow['id'],
												_($myrow['typename']),
												$myrow['transno'],
												ConvertSQLDate($myrow['trandate']),
												$myrow['suppreference'],
												0,
												$myrow['total'],
												$myrow['rate'],
												$myrow['diffonexch'],
												$myrow['diffonexch'],
												$myrow['alloc'],
												'NA');
	}

	/* Now get trans that might have previously been allocated to by this trans
	NB existing entries where still some of the trans outstanding entered from
	above logic will be overwritten with the prev alloc detail below */

	$SQL = "SELECT supptrans.id,
					typename,
					transno,
					trandate,
					suppreference,
					rate,
					ovamount+ovgst AS total,
					diffonexch,
					supptrans.alloc-suppallocs.amt AS prevallocs,
					amt,
					suppallocs.id AS allocid
			FROM supptrans INNER JOIN systypes
			ON supptrans.type = systypes.typeid
			INNER JOIN suppallocs
			ON supptrans.id=suppallocs.transid_allocto
			WHERE suppallocs.transid_allocfrom='" . $_SESSION['AllocTrans'] .
			"' AND supplierno='" . $_SESSION['Alloc']->SupplierID . "'";

	$ErrMsg = _('There was a problem retrieving the previously allocated transactions for modification');

	$DbgMsg = _('The SQL that was used to retrieve the previously allocated transaction information was');

	$Result = DB_query($SQL, $ErrMsg, $DbgMsg);

	while ($myrow = DB_fetch_array($Result)){

		$DiffOnExchThisOne = ($myrow['amt']/$myrow['rate']) - ($myrow['amt']/$_SESSION['Alloc']->TransExRate);

		$_SESSION['Alloc']->add_to_AllocsAllocn ($myrow['id'],
												_($myrow['typename']),
												$myrow['transno'],
												ConvertSQLDate($myrow['trandate']), $myrow['suppreference'], $myrow['amt'],
												$myrow['total'],
												$myrow['rate'],
												$DiffOnExchThisOne,
												($myrow['diffonexch'] - $DiffOnExchThisOne),
												$myrow['prevallocs'],
												$myrow['allocid']);
	}
}

if (isset($_POST['AllocTrans'])){

	echo '<input type="hidden" name="AllocTrans" value="' . $_POST['AllocTrans'] . '" />';

	/*Show the transaction being allocated and the potential trans it could be allocated to
        and those where there is already an existing allocation */

        echo '<div class="row">
				<p>' . _('Allocation of supplier') . ' ' .
        		 $_SESSION['Alloc']->TransTypeName . ' ' . _('number') . ' ' .
        		 $_SESSION['Alloc']->TransNo . ' ' . _('from') . ' ' .
        		 $_SESSION['Alloc']->SupplierID . ' - <b>' .
        		 $_SESSION['Alloc']->SuppName . '</b>, ' . _('dated') . ' ' .
        		 $_SESSION['Alloc']->TransDate;
echo '</p>';
        if ($_SESSION['Alloc']->TransExRate != 1){
	     	  echo '<p>' . _('Amount in supplier currency'). ' <strong>' .
	     	  		 locale_number_format(-$_SESSION['Alloc']->TransAmt,$_SESSION['Alloc']->CurrDecimalPlaces) . '</strong><i> (' .
	     	  		 _('converted into local currency at an exchange rate of') . ' ' .
	     	  		 $_SESSION['Alloc']->TransExRate . ')</i></p>';

        } else {
		     echo '<p>' . _('Transaction total') . ': <strong>' . locale_number_format(-$_SESSION['Alloc']->TransAmt,$_SESSION['Alloc']->CurrDecimalPlaces) . '</strong></p>';
        }
echo '</div>';
    /*Now display the potential and existing allocations put into the array above */

		echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">

			<thead>
				<tr>
							<th>' . _('Type') . '</th>
				 			<th>' . _('Trans') . '<br />' . _('Number') . '</th>
							<th>' . _('Trans')  . '<br />' . _('Date') . '</th>
							<th>' . _('Supp') . '<br />' . _('Ref') . '</th>
							<th>' . _('Total') . '<br />' . _('Amount')  . '</th>
							<th>' . _('Yet to') . '<br />' . _('Allocate') . '</th>
							<th>' . _('This') . '<br />' . _('Allocation') . '</th>
				</tr>
			</thead>
			<tbody>';

		$Counter = 0;
		$TotalAllocated = 0;

		foreach ($_SESSION['Alloc']->Allocs as $AllocnItem) {

	    $YetToAlloc = ($AllocnItem->TransAmount - $AllocnItem->PrevAlloc);

	    echo '<tr class="striped_row">
			<td>' . $AllocnItem->TransType . '</td>
			<td class="number">' . $AllocnItem->TypeNo . '</td>
			<td>' . $AllocnItem->TransDate . '</td>
			<td>' . $AllocnItem->SuppRef . '</td>
			<td class="number">' . locale_number_format($AllocnItem->TransAmount,$_SESSION['Alloc']->CurrDecimalPlaces) . '</td>
			<td class="number">' . locale_number_format($YetToAlloc,$_SESSION['Alloc']->CurrDecimalPlaces) . '<input type="hidden" name="YetToAlloc' . $Counter . '" value="' . $YetToAlloc . '" /></td>';
		 if (ABS($AllocnItem->AllocAmt-$YetToAlloc) < 0.01){
			echo '<td class="number"><input type="checkbox" name="All' .  $Counter . '" checked="checked" />';
	    } else {
	    	echo '<td class="number"><input type="checkbox" name="All' .  $Counter . '" />';
	    }
		echo '<input type="text" class="form-control" name="Amt' . $Counter .'" maxlength="12" size="13" value="' . locale_number_format($AllocnItem->AllocAmt,$_SESSION['Alloc']->CurrDecimalPlaces) . '" /><input type="hidden" name="AllocID' . $Counter .'" value="' . $AllocnItem->ID . '" /></td></tr>';

	    $TotalAllocated = $TotalAllocated + $AllocnItem->AllocAmt;
	    $Counter++;
   }

   echo '</tbody>
		
			<tr>
			<td colspan="6" class="number"><strong>' . _('Total Allocated') . ':</strong></td>
			<td class="number"><strong>' .  locale_number_format($TotalAllocated,$_SESSION['Alloc']->CurrDecimalPlaces) . '</strong></td>
			</tr>
			<tr>
			<td colspan="6" class="number"><b>' . _('Left to allocate') . '</b></td>
			<td class="number"><strong>' . locale_number_format(-$_SESSION['Alloc']->TransAmt - $TotalAllocated,$_SESSION['Alloc']->CurrDecimalPlaces) . '</strong></td>
		</tr>
		
		</table></div></div></div>';

   echo '<div class="row">
			<input type="hidden" name="TotalNumberOfAllocs" value="' . $Counter . '" />
			<br />
	<div class="col-xs-4"><input type="submit" class="btn btn-info" name="RefreshAllocTotal" value="' . _('Recalculate Total To Allocate') . '" /></div>
	<div class="col-xs-4"><input type="submit" class="btn btn-success" name="UpdateDatabase" value="' . _('Process Allocations') . '" /></div>
	
		</div><br />
';

} elseif(isset($_GET['SupplierID'])){

  /*page called with a supplier code  so show the transactions to allocate
  specific to the supplier selected */

  echo '<input type="hidden" name="SupplierID" value="' . $_GET['SupplierID'] . '" />';

  /*Clear any previous allocation records */

  unset($_SESSION['Alloc']);

  $sql = "SELECT id,
		  		transno,
				typename,
				type,
				suppliers.supplierid,
				suppname,
				trandate,
		  		suppreference,
				supptrans.rate,
				ovamount+ovgst AS total,
				alloc,
				decimalplaces AS currdecimalplaces
		  	FROM supptrans INNER JOIN suppliers
		  	ON supptrans.supplierno=suppliers.supplierid
		  	INNER JOIN systypes
		  	ON supptrans.type=systypes.typeid
		  	INNER JOIN currencies
		  	ON suppliers.currcode=currencies.currabrev
		  	WHERE suppliers.supplierid='" . $_GET['SupplierID'] ."'
			AND (supptrans.type=21 OR supptrans.type=22)
			AND settled=0
			ORDER BY id";

  $result = DB_query($sql);
  if (DB_num_rows($result) == 0){
	echo prnMsg(_('There are no outstanding payments or credits yet to be allocated for this supplier'),'info');
	include('includes/footer.php');
	exit;
  }
  echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
';

  $TableHeader = '<thead><tr>
					<th>' . _('Trans Type')  . '</th>
					<th>' . _('Supplier') . '</th>
					<th>' . _('Number') . '</th>
					<th>' . _('Date') .  '</th>
					<th>' . _('Total') . '</th>
					<th>' . _('To Allocate') . '</th>
				</tr></thead>';

  echo $TableHeader;

  /* set up table of TransType - Supplier - Trans No - Date - Total - Left to alloc  */

  $RowCounter = 0;

  while ($myrow = DB_fetch_array($result)) {

	printf('<tr class="striped_row">
			<td>%s</td>
			<td>%s</td>
			<td>%s</td>
			<td>%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td>
			<td><a href="%sAllocTrans=%s" class="btn btn-success">' . _('Allocate')  . '</a></td>
			</tr>',
			_($myrow['typename']),
			$myrow['suppname'],
			$myrow['transno'],
			ConvertSQLDate($myrow['trandate']),
			locale_number_format($myrow['total'],$myrow['currdecimalplaces']),
			locale_number_format($myrow['total']-$myrow['alloc'], $myrow['currdecimalplaces']),
			htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
			$myrow['id']);

  }

} else { /* show all outstanding payments and credits to be allocated */

  /*Clear any previous allocation records */

  unset($_SESSION['Alloc']->Allocs);
  unset($_SESSION['Alloc']);

  $sql = "SELECT id,
		  		transno,
				typename,
				type,
				suppliers.supplierid,
				suppname,
				trandate,
		  		suppreference,
				supptrans.rate,
				ovamount+ovgst AS total,
				alloc,
				decimalplaces AS currdecimalplaces
		  	FROM supptrans INNER JOIN suppliers
			ON supptrans.supplierno=suppliers.supplierid
			INNER JOIN systypes
			ON supptrans.type=systypes.typeid
			INNER JOIN currencies
			ON suppliers.currcode=currencies.currabrev
			WHERE (supptrans.type=21 OR supptrans.type=22)
			AND settled=0
			ORDER BY id";

  $result = DB_query($sql);

  echo '<div class="row gutter30">
<div class="col-xs-12">
<div class="table-responsive">
<table id="general-table" class="table table-bordered">
';
  $TableHeader = '<thead><tr>
					<th>' . _('Trans Type') . '</th>
			  		<th>' . _('Supplier') . '</th>
			  		<th>' . _('Number') . '</th>
			  		<th>' . _('Date') . '</th>
			  		<th>' . _('Total') . '</th>
			  		<th>' . _('To Alloc') . '</th>
					<th>' . _('Action') . '</th>
				</tr></thead>' ;

  echo $TableHeader;

  /* set up table of Tran Type - Supplier - Trans No - Date - Total - Left to alloc  */

  $RowCounter = 0;
  while ($myrow = DB_fetch_array($result)) {

	printf('<tr class="striped_row">
			<td>%s</td>
			<td>%s</td>
			<td>%s</td>
			<td>%s</td>
			<td class="number">%s</td>
			<td class="number">%s</td>
			<td><a href="%sAllocTrans=%s" class="btn btn-success">' . _('Allocate') . '</a></td>
			</tr>',
			_($myrow['typename']),
			$myrow['suppname'],
			$myrow['transno'],
			ConvertSQLDate($myrow['trandate']),
			locale_number_format($myrow['total'],$myrow['currdecimalplaces']),
			locale_number_format($myrow['total']-$myrow['alloc'],$myrow['currdecimalplaces']),
			htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
			$myrow['id']);


  }  //END WHILE LIST LOOP

  echo '</table></div></div></div>';

  if (DB_num_rows($result) == 0) {
	echo prnMsg(_('There are no allocations to be done'),'info');
  }

} /* end of else if not a SupplierID or transaction called with the URL */

echo '
      </form>';
include('includes/footer.php');
?>
