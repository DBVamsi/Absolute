<?php
	require_once '../../required/session.php'; // $Pokemon_Service, $User_Class, $Item_Class, $pdo_instance are available

	if ( !isset($_POST['Action']) )
	{
		echo "
			<tr>
				<td colspan='3'>
					Unable to determine the desired trade action.<br />
					Please try again.
				</td>
			</tr>
		";
		return;
	}

	if ( !isset($_POST['Type']) )
	{
		echo "
			<tr>
				<td colspan='3'>
					Unable to determine the desired object type.<br />
					Please try again.
				</td>
			</tr>
		";
		return;
	}

	if ( !isset($_POST['ID']) ) // This is User_ID on whose side the action is performed
	{
		echo "
			<tr>
				<td colspan='3'>
					Unable to determine the User ID.<br />
					Please try again.
				</td>
			</tr>
		";
		return;
	}

	if ( !isset($_POST['Data']) ) // This is the ID of item/pokemon, or currency data array
	{
		echo "
			<tr>
				<td colspan='3'>
					Unable to determine the desired trade data.<br />
					Please try again.
				</td>
			</tr>
		";
		return;
	}

	$allowed_actions = ['Add', 'Remove'];
	$allowed_types = ['Pokemon', 'Item', 'Currency'];

	$Action_Input = $_POST['Action'] ?? '';
	$Type_Input = $_POST['Type'] ?? '';

	if (!in_array($Action_Input, $allowed_actions, true)) {
		echo "<tr><td colspan='3'>Invalid action specified.</td></tr>";
		return;
	}
	$Action = $Action_Input;

	if (!in_array($Type_Input, $allowed_types, true)) {
		echo "<tr><td colspan='3'>Invalid type specified.</td></tr>";
		return;
	}
	$Type = $Type_Input;

	$User_ID = (int)($_POST['ID']); // User whose items are being manipulated

	// $Data purification depends on $Type
	if ($Type == 'Pokemon' || $Type == 'Item') {
    $Data_ID = isset($_POST['Data']) ? (int)$_POST['Data'] : 0;
	} else if ($Type == 'Currency') {
    $Data_Currency = isset($_POST['Data']) ? Purify($_POST['Data']) : null; // $_POST['Data'] is an array for currency
	} else {
    $Data_ID = isset($_POST['Data']) ? Purify($_POST['Data']) : null; // Default purify if type unknown
	}


	$User = $User_Class->FetchUserData($User_ID);
	if (!$User) {
    echo "<tr><td colspan='3'>Invalid User.</td></tr>";
    return;
  }

	/**
	 * Determine if something is getting added/removed from a certain side of the trade.
	 */
	if ( $User['ID'] == $_SESSION['EvoChroniclesRPG']['Trade']['Sender']['User'] )
	{
		$Side = "Sender";
	}
	else
	{
		$Side = "Recipient";
	}

	/**
	 * Add things to the trade.
	 */
	if ( $Action == 'Add' )
	{
		$Already_Included = false;

		/**
		 * Add Pokemon to the trade.
		 */
		if ( $Type == 'Pokemon' )
		{
			$Pokemon = $Pokemon_Service->GetPokemonData($Data_ID);

			if ( isset($_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon'] as $Key => $Value )
				{
					if ( $Value['ID'] == $Pokemon['ID'] )
					{
						$Already_Included = true;
					}
				}
			}

			if ( !isset($Pokemon) || !$Pokemon )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This Pok&eacute;mon doesn't exist.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Pokemon['Owner_Current'] != $User['ID'] )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This Pok&eacute;mon doesn't belong to {$User['Username']}.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Pokemon['Frozen'] )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This Pok&eacute;mon is frozen and may not be traded.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Already_Included )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This Pok&eacute;mon is already included in the trade.
							</b>
						</td>
					</tr>
				";
			}
			else
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #0f0;'>
								{$Pokemon['Display_Name']} has been added to the trade.
							</b>
						</td>
					</tr>
				";

				$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon'][] = [
					'ID' => $Pokemon['ID'],
				];
			}
		}

		/**
		 * Add Items to the trade.
		 */
		if ( $Type == 'Item' )
		{
			$Item = $Item_Class->FetchOwnedItem($User['ID'], $Data_ID);

			if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items'] as $Key => $Value )
				{
					if ( $Value['ID'] == $Item['ID'] )
					{
						$Already_Included = true;
					}
				}
			}

			if ( !isset($Item) || !$Item )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								The item that you're trying to add, doesn't exist.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Item['Owner'] != $User['ID'] )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This item doesn't belong to {$User['Username']}.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Already_Included )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This item is already included within the trade.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Item['Quantity'] <= 0 )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								This user doesn't own enough of this item.
							</b>
						</td>
					</tr>
				";
			}
			else
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #0f0;'>
								{$Item['Name']} has been added to the trade.
							</b>
						</td>
					</tr>
				";

				$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items'][] = [
					'Row' => $Item['Row'],
					'ID' => $Item['ID'],
					'Quantity' => 1, // Assuming adding 1 at a time, quantity selection would be handled by JS
					'Owner' => $Item['Owner'],
				];
			}
		}

		/**
		 * Add Currencies to the trade.
		 */
		if ( $Type == 'Currency' )
		{
			// $Data_Currency is already purified array
			$Currency_Name_Raw = $Data_Currency['Name'] ?? null; // Example: "currency-td-Money"
			$Currency_Amount = isset($Data_Currency['Amount']) ? (int)$Data_Currency['Amount'] : 0;

      $Currency_Column = '';
      if ($Currency_Name_Raw) {
        $Currency_Parts = explode('-', $Currency_Name_Raw);
        if (count($Currency_Parts) === 3) { // Expecting "currency-td-ActualName"
            $Currency_Column = $Currency_Parts[2];
        }
      }


			if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency'] as $Key => $Value )
				{
					if ( $Value['Currency'] == $Currency_Column )
					{
						$Already_Included = true;
					}
				}
			}

      // Whitelist check for currency column name
      $valid_currencies = ['Money', 'Abso_Coins']; // Define allowed currency types
      if (!in_array($Currency_Column, $valid_currencies)) {
        echo "<tr><td colspan='3' style='padding: 10px;'><b style='color: #f00;'>Invalid currency type.</b></td></tr>";
      }
			else if ( $Currency_Amount <= 0 )
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								Please add currency to the trade at a value greater than 0.
							</b>
						</td>
					</tr>
				";
			}
      // FetchUserCurrencies would be ideal from UserService, using direct $pdo_instance for now
			else if ( $Currency_Amount > $User[$Currency_Column] ) // Assuming $User from FetchUserData has currency fields
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								{$User['Username']} does not have enough {$Currency_Column} to add to the trade.
							</b>
						</td>
					</tr>
				";
			}
			else if ( $Already_Included )
			{
				echo "
				<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #f00;'>
								You have already added {$Currency_Column} to {$User['Username']}'s side of the trade.
							</b>
						</td>
					</tr>
				";
			}
			else
			{
				echo "
					<tr>
						<td colspan='3' style='padding: 10px;'>
							<b style='color: #0f0;'>
								" . number_format($Currency_Amount) . " {$Currency_Column} has been added to the trade.
							</b>
						</td>
					</tr>
				";

				$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency'][] = [
					'Currency' => $Currency_Column,
					'Quantity' => $Currency_Amount,
				];
			}
		}
	}

	/**
	 * Remove things from the trade.
	 */
	else if ( $Action == 'Remove' )
	{
		// $Data_ID or $Data_Currency (if type is currency string) would be used here
    $Removal_Target_ID = null;
    if ($Type == 'Pokemon' || $Type == 'Item') {
        $Removal_Target_ID = $Data_ID;
    } else if ($Type == 'Currency') {
        // $Data was purified as a string, it should be the currency name for removal
        $Removal_Target_ID = isset($_POST['Data']) ? Purify($_POST['Data']) : null;
    }


		if ( $Type == 'Pokemon' )
		{
			if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon'] as $Key => $Pokemon )
				{
					if ( $Pokemon['ID'] == $Removal_Target_ID )
					{
						$Poke_Data = $Pokemon_Service->GetPokemonData($Pokemon['ID']);

						array_splice(
							$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon'],
							$Key,
							1
						);

						echo "
							<tr>
								<td colspan='3' style='padding: 10px;'>
									<b style='color: #0f0;'>
										You have removed the {$Poke_Data['Display_Name']} from this side of the trade.
									</b>
								</td>
							</tr>
						";
					}
				}
			}
		}

		if ( $Type == 'Item' )
		{
			if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items'] as $Key => $Item )
				{
					if ( $Item['ID'] == $Removal_Target_ID )
					{
						$Item_Data = $Item_Class->FetchItemData($Item['ID']);

						array_splice(
							$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items'],
							$Key,
							1
						);

						echo "
							<tr>
								<td colspan='3' style='padding: 10px;'>
									<b style='color: #0f0;'>
										You have removed the {$Item_Data['Name']} from this side of the trade.
									</b>
								</td>
							</tr>
						";
					}
				}
			}
		}

		if ( $Type == 'Currency' )
		{
			if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency']) )
			{
				foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency'] as $Key => $Currencies )
				{
					if ( $Currencies['Currency'] === $Removal_Target_ID ) // $Removal_Target_ID is currency name string here
					{
						array_splice(
							$_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency'],
							$Key, // Use $Key instead of 0
							1
						);

						echo "
							<tr>
								<td colspan='3' style='padding: 10px;'>
									<b style='color: #0f0;'>
										You have removed the {$Removal_Target_ID} from this side of the trade.
									</b>
								</td>
							</tr>
						";
					}
				}
			}
		}
	}

	/**
	 * Display the current content of the trade.
	 */
	if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency']) )
	{
		foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Currency'] as $Key => $Currencies )
		{
			echo "
				<tr>
					<td colspan='1' style='width: 76px;'>
						<img src='" . DOMAIN_SPRITES . "/Assets/{$Currencies['Currency']}.png' />
					</td>
					<td colspan='1'>
						" . number_format($Currencies['Quantity']) . "
					</td>
					<td colspan='1' style='width: 76px;'>
						<button
							onclick='Remove_From_Trade({$User_ID}, \"Currency\", \"{$Currencies['Currency']}\");'
							style='width: 70px;'
						>
							Remove
						</button>
					</td>
				</tr>
			";
		}
	}

	if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items']) )
	{
		foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Items'] as $Key => $Items )
		{
			$Item_Data = $Item_Class->FetchItemData($Items['ID']);

			echo "
				<tr>
					<td colspan='1' style='width: 76px;'>
						<img src='{$Item_Data['Icon']}' />
					</td>
					<td colspan='1'>
						{$Item_Data['Name']}
						<br />
						x" . number_format($Items['Quantity']) . "
					</td>
					<td colspan='1' style='width: 76px;'>
						<button
							onclick='Remove_From_Trade({$User_ID}, \"Item\", \"{$Item_Data['ID']}\");'
							style='width: 70px;'
						>
							Remove
						</button>
					</td>
				</tr>
			";
		}
	}

	if ( isset( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon']) )
	{
		foreach ( $_SESSION['EvoChroniclesRPG']['Trade'][$Side]['Pokemon'] as $Key => $Pokemon )
		{
			$Pokemon_Data = $Pokemon_Service->GetPokemonData($Pokemon['ID']);

			echo "
				<tr>
					<td colspan='1' style='width: 76px;'>
						<img src='{$Pokemon_Data['Icon']}' />
						<img src='{$Pokemon_Data['Gender_Icon']}' style='height: 20px; width: 20px;' />
					</td>
					<td colspan='1'>
						{$Pokemon_Data['Display_Name']} (Level: " . $Pokemon_Data['Level'] . ")
						<br />
						" . ($Pokemon_Data['Item'] ? $Pokemon_Data['Item_Name'] : '') . "
					</td>
					<td colspan='1' style='width: 76px;'>
						<button
							onclick='Remove_From_Trade({$User_ID}, \"Pokemon\", \"{$Pokemon_Data['ID']}\");'
							style='width: 70px;'
						>
							Remove
						</button>
					</td>
				</tr>
			";
		}
	}
