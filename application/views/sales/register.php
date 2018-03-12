<?php $this->load->view("partial/header"); ?>

<?php
if(isset($error))
{
	echo "<div class='alert alert-dismissible alert-danger'>".$error."</div>";
}

if(!empty($warning))
{
	echo "<div class='alert alert-dismissible alert-warning'>".$warning."</div>";
}

if(isset($success))
{
	echo "<div class='alert alert-dismissible alert-success'>".$success."</div>";
}
?>

<div id="register_wrapper">

<!-- Top register controls -->

	<?php echo form_open($controller_name."/change_mode", array('id'=>'mode_form', 'class'=>'form-horizontal panel panel-default')); ?>
		<div class="panel-body form-group">
			<ul>
				<li class="pull-left first_li">
					<label class="control-label"><?php echo $this->lang->line('sales_mode'); ?></label>
				</li>
				<li class="pull-left">
					<?php echo form_dropdown('mode', $modes, $mode, array('onchange'=>"$('#mode_form').submit();", 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'fit')); ?>
				</li>
				<?php
				if($this->config->item('dinner_table_enable') == TRUE)
				{
				?>
					<li class="pull-left first_li">
						<label class="control-label"><?php echo $this->lang->line('sales_table'); ?></label>
					</li>
					<li class="pull-left">
						<?php echo form_dropdown('dinner_table', $empty_tables, $selected_table,array('onchange'=>"$('#mode_form').submit();", 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'fit')); ?>
					</li>
				<?php
				}
				if(count($stock_locations) > 1)
				{
				?>
					<li class="pull-left">
						<label class="control-label"><?php echo $this->lang->line('sales_stock_location'); ?></label>
					</li>
					<li class="pull-left">
						<?php echo form_dropdown('stock_location', $stock_locations, $stock_location, array('onchange'=>"$('#mode_form').submit();", 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'fit')); ?>
					</li>
				<?php
				}
				?>

				<li class="pull-right">
					<button class='btn btn-default btn-sm modal-dlg' id='show_suspended_sales_button' data-href='<?php echo site_url($controller_name."/suspended"); ?>'
							title='<?php echo $this->lang->line('sales_suspended_sales'); ?>'>
						<span class="glyphicon glyphicon-align-justify">&nbsp</span><?php echo $this->lang->line('sales_suspended_sales'); ?>
					</button>
				</li>
			
				<?php
				if($this->Employee->has_grant('reports_sales', $this->session->userdata('person_id')))
				{
				?>
					<li class="pull-right">						
						<?php echo anchor($controller_name."/manage", '<span class="glyphicon glyphicon-list-alt">&nbsp</span>' . $this->lang->line('sales_takings'), 
									array('class'=>'btn btn-primary btn-sm', 'id'=>'sales_takings_button', 'title'=>$this->lang->line('sales_takings'))); ?>
					</li>
				<?php
				}
				?>
			</ul>
		</div>
	<?php echo form_close(); ?>

	<?php $tabindex = 0; ?>

	<?php echo form_open($controller_name."/add", array('id'=>'add_item_form', 'class'=>'form-horizontal panel panel-default')); ?>
		<div class="panel-body form-group">
			<ul>
				<li class="pull-left first_li">
					<label for="item" class='control-label'><?php echo $this->lang->line('sales_find_or_scan_item_or_receipt'); ?></label>
				</li>
				<li class="pull-left">
					<?php echo form_input(array('name'=>'item', 'id'=>'item', 'class'=>'form-control input-sm', 'size'=>'50', 'tabindex'=>++$tabindex)); ?>
					<span class="ui-helper-hidden-accessible" role="status"></span>
				</li>
				<li class="pull-right">
					<button id='new_item_button' class='btn btn-info btn-sm pull-right modal-dlg' data-btn-new='<?php echo $this->lang->line('common_new') ?>' data-btn-submit='<?php echo $this->lang->line('common_submit')?>' data-href='<?php echo site_url("items/view"); ?>'
							title='<?php echo $this->lang->line($controller_name . '_new_item'); ?>'>
						<span class="glyphicon glyphicon-tag">&nbsp</span><?php echo $this->lang->line($controller_name. '_new_item'); ?>
					</button>
				</li>
			</ul>
		</div>
	<?php echo form_close(); ?>


<!-- Sale Items List -->
	
	<table class="sales_table_100" id="register">
		<thead>
			<tr>
				<th style="width: 5%;"><?php echo $this->lang->line('common_delete'); ?></th>
				<th style="width: 15%;"><?php echo $this->lang->line('sales_item_number'); ?></th>
				<th style="width: 35%;"><?php echo $this->lang->line('sales_item_name'); ?></th>
				<th style="width: 10%;"><?php echo $this->lang->line('sales_price'); ?></th>
				<th style="width: 10%;"><?php echo $this->lang->line('sales_quantity'); ?></th>
				<th style="width: 10%;"><?php echo $this->lang->line('sales_discount'); ?></th>
				<th style="width: 10%;"><?php echo $this->lang->line('sales_total'); ?></th>
				<th style="width: 5%;"><?php echo $this->lang->line('sales_update'); ?></th>
			</tr>
		</thead>

		<tbody id="cart_contents">
			<?php
			if(count($cart) == 0)
			{
			?>
				<tr>
					<td colspan='8'>
						<div class='alert alert-dismissible alert-info'><?php echo $this->lang->line('sales_no_items_in_cart'); ?></div>
					</td>
				</tr>
			<?php
			}
			else
			{				
				foreach(array_reverse($cart, TRUE) as $line=>$item)
				{					
			?>
					<?php echo form_open($controller_name."/edit_item/$line", array('class'=>'form-horizontal', 'id'=>'cart_'.$line)); ?>
						<tr>
							<td><?php echo anchor($controller_name."/delete_item/$line", '<span class="glyphicon glyphicon-trash"></span>');?></td>
							<td><?php echo $item['item_number']; ?></td>
							<td style="align: center;">
								<?php echo $item['name']; ?><br /> <?php if($item['stock_type'] == '0'): echo '[' . to_quantity_decimals($item['in_stock']) . ' in ' . $item['stock_name'] . ']'; endif; ?>
								<?php echo form_hidden('location', $item['item_location']); ?>
							</td>

							<?php
							if($items_module_allowed)
							{
							?>
								<td><?php echo form_input(array('name'=>'price', 'class'=>'form-control input-sm', 'value'=>to_currency_no_money($item['price']), 'tabindex'=>++$tabindex));?></td>
							<?php
							}
							else
							{
							?>
								<td>
									<?php echo to_currency($item['price']); ?>
									<?php echo form_hidden('price', to_currency_no_money($item['price'])); ?>
								</td>
							<?php
							}
							?>

							<td>
								<?php
								if($item['is_serialized']==1)
								{
									echo to_quantity_decimals($item['quantity']);
									echo form_hidden('quantity', $item['quantity']);
								}
								else
								{								
									echo form_input(array('name'=>'quantity', 'class'=>'form-control input-sm', 'value'=>to_quantity_decimals($item['quantity']), 'tabindex'=>++$tabindex));
								}
								?>
							</td>

							<td><?php echo form_input(array('name'=>'discount', 'class'=>'form-control input-sm', 'value'=>to_decimals($item['discount'], 0), 'tabindex'=>++$tabindex));?></td>
							<td><?php echo to_currency($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100); ?></td>
							<td><a href="javascript:document.getElementById('<?php echo 'cart_'.$line ?>').submit();" title=<?php echo $this->lang->line('sales_update')?> ><span class="glyphicon glyphicon-refresh"></span></a></td>
						</tr>
						<tr>
							<?php 
							if($item['allow_alt_description']==1)
							{
							?>
								<td style="color: #2F4F4F;"><?php echo $this->lang->line('sales_description_abbrv');?></td>
							<?php 
							}
							?>

							<td colspan='2' style="text-align: left;">
								<?php
								if($item['allow_alt_description']==1)
								{
									echo form_input(array('name'=>'description', 'class'=>'form-control input-sm', 'value'=>$item['description']));
								}
								else
								{
									if($item['description']!='')
									{
										echo $item['description'];
										echo form_hidden('description', $item['description']);
									}
									else
									{
										echo $this->lang->line('sales_no_description');
										echo form_hidden('description','');
									}
								}
								?>
							</td>
							<td>&nbsp;</td>
							<td style="color: #2F4F4F;">
								<?php
								if($item['is_serialized']==1)
								{
									echo $this->lang->line('sales_serial');
								}
								?>
							</td>
							<td colspan='4' style="text-align: left;">
								<?php
								if($item['is_serialized']==1)
								{
									echo form_input(array('name'=>'serialnumber', 'class'=>'form-control input-sm', 'value'=>$item['serialnumber']));
								}
								else
								{
									echo form_hidden('serialnumber', '');
								}
								?>
							</td>
						</tr>
					<?php echo form_close(); ?>
			<?php
				}
			}
			?>
		</tbody>
	</table>
</div>

<!-- Overall Sale -->

<div id="overall_sale" class="panel panel-default">
	<div class="panel-body">
		<?php
		if(isset($customer))
		{
		?>
			<table class="sales_table_100">
				<tr>
					<th style='width: 55%;'><?php echo $this->lang->line("sales_customer"); ?></th>
					<th style="width: 45%; text-align: right;"><?php echo $customer; ?></th>
				</tr>
				<?php
				if(!empty($customer_email))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_email"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $customer_email; ?></th>
					</tr>
				<?php
				}
				?>
				<?php
				if(!empty($customer_address))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_address"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $customer_address; ?></th>
					</tr>
				<?php
				}
				?>
				<?php
				if(!empty($customer_location))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_location"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $customer_location; ?></th>
					</tr>
				<?php
				}
				?>
				<tr>
					<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_discount"); ?></th>
					<th style="width: 45%; text-align: right;"><?php echo $customer_discount_percent . ' %'; ?></th>
				</tr>
				<?php if($this->config->item('customer_reward_enable') == TRUE): ?>
				<?php
				if(!empty($customer_rewards))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("rewards_package"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $customer_rewards['package_name']; ?></th>
					</tr>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("customers_available_points"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $customer_rewards['points']; ?></th>
					</tr>
				<?php
				}
				?>
				<?php endif; ?>
				<tr>
					<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_total"); ?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency($customer_total); ?></th>
				</tr>
				<?php
				if(!empty($mailchimp_info))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_mailchimp_status"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $mailchimp_info['status']; ?></th>
					</tr>
				<?php
				}
				?>
			</table>

			<?php echo anchor($controller_name."/remove_customer", '<span class="glyphicon glyphicon-remove">&nbsp</span>' . $this->lang->line('common_remove').' '.$this->lang->line('customers_customer'),
								array('class'=>'btn btn-danger btn-sm', 'id'=>'remove_customer_button', 'title'=>$this->lang->line('common_remove').' '.$this->lang->line('customers_customer'))); ?>
		<?php
		}
		else
		{
		?>
			<?php echo form_open($controller_name."/select_customer", array('id'=>'select_customer_form', 'class'=>'form-horizontal')); ?>
				<div class="form-group" id="select_customer">
					<label id="customer_label" for="customer" class="control-label" style="margin-bottom: 1em; margin-top: -1em;"><?php echo $this->lang->line('sales_select_customer'); ?></label>
					<?php echo form_input(array('name'=>'customer', 'id'=>'customer', 'class'=>'form-control input-sm', 'value'=>$this->lang->line('sales_start_typing_customer_name')));?>

					<button class='btn btn-info btn-sm modal-dlg' data-btn-submit='<?php echo $this->lang->line('common_submit') ?>' data-href='<?php echo site_url("customers/view"); ?>'
							title='<?php echo $this->lang->line($controller_name. '_new_customer'); ?>'>
						<span class="glyphicon glyphicon-user">&nbsp</span><?php echo $this->lang->line($controller_name. '_new_customer'); ?>
					</button>

				</div>
			<?php echo form_close(); ?>
		<?php
		}
		?>

		<table class="sales_table_100" id="sale_totals">
			<tr>
				<th style="width: 55%;"><?php echo $this->lang->line('sales_sub_total'); ?></th>
				<th style="width: 45%; text-align: right;"><?php echo to_currency($subtotal); ?></th>
			</tr>
			
			<?php
			foreach($taxes as $tax_group_index=>$sales_tax)
			{
			?>
				<tr>
					<th style='width: 55%;'><?php echo $sales_tax['tax_group']; ?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency_tax($sales_tax['sale_tax_amount']); ?></th>
				</tr>
			<?php
			}
			?>

			<tr>
				<th style='width: 55%;'><?php echo $this->lang->line('sales_total'); ?></th>
				<th style="width: 45%; text-align: right;"><span id="sale_total"><?php echo to_currency($total); ?></span></th>
			</tr>
		</table>
	
		<?php
		// Only show this part if there are Items already in the sale.
		if(count($cart) > 0)
		{
		?>
			<table class="sales_table_100" id="payment_totals">
				<tr>
					<th style="width: 55%;"><?php echo $this->lang->line('sales_payments_total');?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency($payments_total); ?></th>
				</tr>
				<tr>
					<th style="width: 55%;"><?php echo $this->lang->line('sales_amount_due');?></th>
					<th style="width: 45%; text-align: right;"><span id="sale_amount_due"><?php echo to_currency($amount_due); ?></span></th>
				</tr>
			</table>

			<div id="payment_details">
				<?php
				// Show Complete sale button instead of Add Payment if there is no amount due left
				if($payments_cover_total)
				{
				?>
					<?php echo form_open($controller_name."/add_payment", array('id'=>'add_payment_form', 'class'=>'form-horizontal')); ?>
						<table class="sales_table_100">
							<tr>
								<td><?php echo $this->lang->line('sales_payment');?></td>
								<td>
									<?php echo form_dropdown('payment_type', $payment_options, $selected_payment_type, array('id'=>'payment_types', 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'auto', 'disabled'=>'disabled')); ?>
								</td>
							</tr>
							<tr>
								<td><span id="amount_tendered_label"><?php echo $this->lang->line('sales_amount_tendered'); ?></span></td>
								<td>
									<?php echo form_input(array('name'=>'amount_tendered', 'id'=>'amount_tendered', 'class'=>'form-control input-sm disabled', 'disabled'=>'disabled', 'value'=>'0', 'size'=>'5', 'tabindex'=>++$tabindex)); ?>
								</td>
							</tr>
						</table>
					<?php echo form_close(); ?>
	   					<?php
						// Only show this part if the payment cover the total and in sale or return mode
						if($sales_or_return_mode == '1')
						{
						?>
  						<div class='btn btn-sm btn-success pull-right' id='finish_sale_button' tabindex='<?php echo ++$tabindex; ?>'><span class="glyphicon glyphicon-ok">&nbsp</span><?php echo $this->lang->line('sales_complete_sale'); ?></div>
						<?php
						}
	   					?>
 				<?php
				}
				else
				{
				?>
					<?php echo form_open($controller_name."/add_payment", array('id'=>'add_payment_form', 'class'=>'form-horizontal')); ?>
						<table class="sales_table_100">
							<tr>
								<td><?php echo $this->lang->line('sales_payment');?></td>
								<td>
									<?php echo form_dropdown('payment_type', $payment_options,  $selected_payment_type, array('id'=>'payment_types', 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'fit')); ?>
								</td>
							</tr>
							<tr>
								<td><span id="amount_tendered_label"><?php echo $this->lang->line('sales_amount_tendered'); ?></span></td>
								<td>
									<?php echo form_input(array('name'=>'amount_tendered', 'id'=>'amount_tendered', 'class'=>'form-control input-sm non-giftcard-input', 'value'=>to_currency_no_money($amount_due), 'size'=>'5', 'tabindex'=>++$tabindex)); ?>
									<?php echo form_input(array('name'=>'amount_tendered', 'id'=>'amount_tendered', 'class'=>'form-control input-sm giftcard-input', 'disabled' => true, 'value'=>to_currency_no_money($amount_due), 'size'=>'5', 'tabindex'=>++$tabindex)); ?>
								</td>
							</tr>
						</table>
					<?php echo form_close(); ?>

					<div class='btn btn-sm btn-success pull-right' id='add_payment_button' tabindex='<?php echo ++$tabindex; ?>'><span class="glyphicon glyphicon-credit-card">&nbsp</span><?php echo $this->lang->line('sales_add_payment'); ?></div>
				<?php
				}
				?>

				<?php
				// Only show this part if there is at least one payment entered.
				if(count($payments) > 0)
				{
				?>
					<table class="sales_table_100" id="register">
						<thead>
							<tr>
								<th style="width: 10%;"><?php echo $this->lang->line('common_delete'); ?></th>
								<th style="width: 60%;"><?php echo $this->lang->line('sales_payment_type'); ?></th>
								<th style="width: 20%;"><?php echo $this->lang->line('sales_payme