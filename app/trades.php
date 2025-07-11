<?php
	require_once 'core/required/layout_top.php';
?>

<div class='panel content'>
	<div class='head'>Trade Center</div>
	<div class='body'>
		<div class='nav'>
			<div>
				<a href='javascript:void(0);' onclick="Tab('pending');">
					Pending Trades
				</a>
			</div>
			<div>
				<a href='javascript:void(0);' onclick="Tab('history');">
					Trade History
				</a>
			</div>
		</div>

		<div id='TradeAJAX' style='display: flex; flex-direction: row; flex-wrap: wrap; justify-content: center;'>
			<?php
				if ( !isset($_GET['Action']) && !isset($_GET['id']) )
				{
			?>

				<div data-id='left-col' style='flex-basis: 49%; margin: 5px 3px;'>
					<div class='description'>Enter another user's ID to begin a trade with them.</div>

					<table class='border-gradient' style='width: 100%;'>
						<thead>
							<tr>
								<th colspan='1'>
									Create A Trade
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<input type='text' placeholder='User ID' id='recipientID' />
								</td>
							</tr>
							<tr>
								<td>
									<button onclick='TradePrepare();'>
										Begin A Trade
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div data-id='right-col' style='flex-basis: 49%; margin: 5px 3px;'>
					<div class='description'>All pending trades that involve you are listed below.</div>

					<?php
						try
						{
							$Pending_Query = $PDO->prepare("
                SELECT `ID`, `Sender`, `Recipient`, `Status`
                FROM `trades`
                WHERE (`Sender` = ? OR `Recipient` = ?) AND `Status` = ?
              ");
							$Pending_Query->execute([ $User_Data['ID'], $User_Data['ID'], 'Pending' ]);
							$Pending_Query->setFetchMode(PDO::FETCH_ASSOC);
							$Pending_Trades = $Pending_Query->fetchAll();
						}
						catch ( PDOException $e )
						{
							HandleError($e);
						}

						if ( empty($Pending_Trades) )
						{
							$Trade_Text = "
								<tr>
									<td colspan='3' style='padding: 7px;'>
										You do not currently have any pending trades.
									</td>
								</tr>
							";
						}
						else
						{
							$Trade_Text = "
								<tbody>
									<td colspan='1' style='padding: 7px;'>
										<b>Trade ID</b>
									</td>
									<td colspan='1' style='padding: 7px;'>
										<b>Sender</b>
									</td>
									<td colspan='1' style='padding: 7px;'>
										<b>Recipient</b>
									</td>
								</tbody>
							";

							foreach( $Pending_Trades as $Key => $Value )
							{
								$Sender = $User_Class->FetchUserData($Value['Sender']);
								$Sender_Username = $User_Class->DisplayUserName($Sender['ID']);

								$Recipient = $User_Class->FetchUserData($Value['Receiver']);
								$Recipient_Username = $User_Class->DisplayUserName($Recipient['ID']);

								$Trade_Text .= "
									<tr>
										<td style='padding: 6px;'>
											<a href='javascript:void(0);' onclick='TradeView({$Value['ID']});'>#" . number_format($Value['ID']) . "</a>
										</td>
										<td style='padding: 6px;'>
											<a href='" . DOMAIN_ROOT . "/profile.php?id={$Sender['ID']}'>{$Sender_Username}</a>
										</td>
										<td style='padding: 6px;'>
											<a href='" . DOMAIN_ROOT . "/profile.php?id={$Recipient['ID']}'>{$Recipient_Username}</a>
										</td>
									</tr>
								";
							}
						}
					?>

					<table class='border-gradient' style='width: 100%;'>
						<thead>
							<tr>
								<th colspan='3'>
									Pending Trades
								</th>
							</tr>
						</thead>
						<tbody>
							<?= $Trade_Text; ?>
						</tbody>
					</table>
				</div>

			<?php
				}
				else
				{
			?>

				<div class='description' style='margin-bottom: 5px;'>Preparing your trade!</div>
				<div style='padding: 5px;'>
					<div class='spinner' style='left: 48%; position: relative;'></div><br />
				</div>

			<?php
				}
			?>
		</div>
	</div>
</div>

<script type='text/javascript'>
	<?php
		/**
		 * If a trade has been initiated via a URL redirect, trigger the proper function.
		 */
		if ( isset($_GET['Action']) && isset($_GET['ID']) )
		{
			$Action = Purify($_GET['Action']); // Whitelisted string, Purify is fine
			$User_ID = isset($_GET['ID']) ? (int)$_GET['ID'] : 0;

			if ( $Action == "Create" && $User_ID > 0 ) // Ensure User_ID is valid before using
			{
				echo "TradePrepare({$User_ID});";
			}
		}
	?>

	/**
	 * Swap between each tab's contents.
	 */
	function Tab(Path)
	{
		$.get('core/ajax/trading/tabs/' + Path + '.php', function(data)
		{
			$('#TradeAJAX').html(data);
		});
	}

	function TradeView(Trade_ID)
	{
		$.ajax({
			type: 'POST',
			url: '<?= DOMAIN_ROOT; ?>/core/ajax/trading/view.php',
			data: { Trade_ID: Trade_ID },
			success: function(data)
			{
				$('#TradeAJAX').html(data);
			},
			error: function(data)
			{
				$('#TradeAJAX').html(data);
			}
		});
	}

	function TradeManage(Trade_ID, Action)
	{
		$.ajax({
			type: 'POST',
			url: '<?= DOMAIN_ROOT; ?>/core/ajax/trading/manage.php',
			data: { Trade_ID: Trade_ID, Action: Action },
			success: function(data)
			{
				$('#TradeAJAX').html(data);
			},
			error: function(data)
			{
				$('#TradeAJAX').html(data);
			}
		});
	}

	/**
	 * Show the proper UI if you're preparing to send a trade.
	 */
	function TradePrepare(ID)
	{
		let Recipient = 0;
		if ( ID == null )
		{
			Recipient = $('input#recipientID').val();
		}
		else
		{
			Recipient = ID;
		}

		$.ajax({
			type: 'POST',
			url: '<?= DOMAIN_ROOT; ?>/core/ajax/trading/prepare.php',
			data: { ID: Recipient },
			success: function(data)
			{
				$('#TradeAJAX').html(data);
			},
			error: function(data)
			{
				$('#TradeAJAX').html(data);
			}
		});
	}

	/**
	 * Create the trade.
 	 */
	function TradeCreate()
	{
		$.ajax({
			type: 'POST',
			url: '<?= DOMAIN_ROOT; ?>/core/ajax/trading/create.php',
			data: { },
			success: function(data)
			{
				$('#TradeAJAX').html(data);
			},
			error: function(data)
			{
				$('#TradeAJAX').html(data);
			}
		});
	}

	/**
	 * Add Pokemon, Items, or Currency from the trade.
	 */
	function Add_To_Trade(User_ID, Action, Type, Data = null)
	{
		if ( Type == 'Currency' )
		{
			let Input_ID = Data;
			Data = {
				"Name": $('#TabContent' + User_ID + ' #' + Data).attr('ID'),
				"Amount": $('#TabContent' + User_ID + ' #' + Data).val(),
			};
			$('#TabContent' + User_ID + ' #' + Input_ID).val('');
		}

		$.ajax({
      url: 'core/ajax/trading/action.php',
      type: 'POST',
			data: { ID: User_ID, Action: Action, Type: Type, Data: Data },
			success: function(data)
			{
				$('#TradeIncluded' + User_ID).html(data);
			},
			error: function(data)
			{
				$('#TradeIncluded' + User_ID).html(data);
			}
		});
	}

	/**
	 * Remove an object from the trade.
	 */
	function Remove_From_Trade(User_ID, Type, Name)
	{
		$.ajax({
      url: 'core/ajax/trading/action.php',
      type: 'POST',
			data: { Action: 'Remove', ID: User_ID, Type: Type, Data: Name },
			success: function(data)
			{
				$('#TradeIncluded' + User_ID).html(data);
			},
			error: function(data)
			{
				$('#TradeIncluded' + User_ID).html(data);
			}
		});
	}

	/**
	 * Swap through the tabs: Pokemon, Inventory, and Currency.
	 */
	function Change_Tab(Tab, User_ID)
	{
		$.ajax({
      url: 'core/ajax/trading/' + Tab + '.php',
      type: 'POST',
			data: { tab: Tab, id: User_ID },
			success: function(data)
			{
				$('#TabContent' + User_ID).html(data);
			},
			error: function(data)
			{
				$('#TabContent' + User_ID).html(data);
			}
		});
	}

	function Update_Box(Page = 1, User_ID)
  {
    $.ajax({
      url: 'core/ajax/trading/box.php',
      type: 'POST',
      data: {
        id: User_ID,
        Page: Page
      },
      success: function(data)
      {
        $('#TabContent' + User_ID).html(data);
      },
      error: function(data)
      {
        $('#TabContent' + User_ID).html(data);
      }
    });
  }
</script>

<?php
	require_once 'core/required/layout_bottom.php';
