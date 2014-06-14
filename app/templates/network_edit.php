<div class="container">
	<h1>Network interface</h1>
	<?php if ($this->nic->wireless === 1): ?>
		<legend>Wi-Fi networks in range</legend>
			<div class="boxed">
			<?php foreach ($this->wlans->{$this->arg} as $key => $value): ?>
				<p><a href="/network/wlan/<?=$this->arg ?>/<?=$value->ESSID ?>" class="btn btn-lg btn-default btn-block"><?php if ($this->nic->currentssid === $value->ESSID): ?><i class="fa fa-check green dx"></i>&nbsp;&nbsp;&nbsp;<?php endif; ?><strong><?=$value->ESSID ?></strong><?php if ($value->{'Encryption key'} === 'on'): ?><i class="fa fa-lock dx"></i>&nbsp;&nbsp;&nbsp;<?php endif; ?></a></p>
			<?php endforeach; ?>
			</div>
		</fieldset>
	<div>
			<label for="wifiProfiles" >Show Wi-Fi stored profiles</label>
			<div class="">
				<label class="switch-light well" onclick="">
					<input id="wifiProfiles" name="features[airplay][enable]" type="checkbox" value="1"<?php if($this->wifiprofiles['enable'] == 1): ?> checked="checked" <?php endif ?>>
					<span><span></span><span></span></span><a class="btn btn-primary"></a>
				</label>
				<span class="help-block">Show / create / edit / delete, Wi-Fi profiles.</span>
			</div>
	</div>
	<div class="boxed hide" id="wifiProfilesBox">
	<?php foreach ($this->wlan_profiles as $profile): ?>
		<p><a href="/network/wlan/<?=$this->arg ?>/<?=$profile->ssid ?>" class="btn btn-lg btn-default btn-block"><?php if ($this->nic->currentssid === $profile->ssid): ?><i class="fa fa-check green dx"></i>&nbsp;&nbsp;&nbsp;<?php endif; ?><strong><?=$profile->ssid ?></strong><?php if ($profile->encryption !== 'none'): ?><i class="fa fa-lock dx"></i>&nbsp;&nbsp;&nbsp;<?php endif; ?></a></p>
	<?php endforeach; ?>
	<a href="/network/wlan/add" class="btn btn-primary" >Add WiFi Profile</a>
	</div>
	<?php endif ?>
	<form class="form-horizontal" action="/network" method="post" data-parsley-validate>
		<input type="hidden" name="nic[name]" value="<?=$this->arg ?>" />
		<fieldset>
			<legend>Interface information</legend>
			<div class="boxed">
				<table class="info-table">
					<tbody>
						<tr><th>Interface name:</th><td><strong><?=$this->arg ?></strong></td></tr>
						<tr><th>Interface type:</th><td><?php if ($this->nic->wireless == 1): ?>wireless<?php else: ?>wired ethernet<?php endif ?></td></tr>
						<?php if(isset($this->nic->currentssid) && $this->nic->currentssid !== 'off/any'): ?><tr><th>WiFi Associated SSID:</th><td><strong><?=$this->nic->currentssid ?></strong></td></tr><?php endif; ?>
						<tr><th>Assigned IP address:</th><td><strong><?=$this->nic->ip ?></strong></td></tr>
						<tr><th>Interface speed:</th><td><?=$this->nic->speed ?><i class="fa <?php if ($this->nic->speed !== ' Unknown!' && $this->nic->speed !== null): ?>fa-check green<?php else: ?>fa-times red<?php endif; ?> dx"></i></td></tr>
						<tr><th><a href="/network"><i class="fa fa-arrow-left sx"></i> back to the list</a></th><td></td></tr>
					</tbody>
				</table>
			</div>
		</fieldset>
		<!-- <p>If you mess up with this configuration you can <a data-toggle="modal" href="#net-config-defaults">reset to default</a>.</p> -->
		<fieldset>
			<legend>Interface configuration</legend>
			<div class="form-group">
				<label class="col-sm-2 control-label" for="nic[dhcp]">IP assignment</label>
				<div class="col-sm-10">
					<select id="dhcp" name="nic[dhcp]" class="selectpicker" data-style="btn-default btn-lg">
						<option value="1" <?php if ($this->{$this->uri(3)}->dhcp === '1'): ?> selected <?php endif; ?>>DHCP</option>
						<option value="0" <?php if ($this->{$this->uri(3)}->dhcp === '0'): ?> selected <?php endif; ?>>Static</option>
					</select>
					<span class="help-block">Choose between DHCP and Static configuration</span>
				</div>
			</div>
			<div id="network-manual-config" class="hide">		
				<div class="form-group">
					<label class="col-sm-2 control-label" for="nic[ip]">IP address</label>
					<div class="col-sm-10">
						<input class="form-control input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="address" name="nic[ip]" value="<?=$this->nic->ip ?>" placeholder="<?=$this->nic->ip ?>" data-parsley-trigger="change" required />
						<span class="help-block">Manually set the IP address.</span>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label" for="nic[netmask]">Netmask</label>
					<div class="col-sm-10">
						<input class="form-control input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="netmask" name="nic[netmask]" value="<?=$this->nic->netmask ?>" data-parsley-trigger="change" placeholder="<?=$this->nic->netmask ?>" required />
						<span class="help-block">Manually set the network mask.</span>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label" for="nic[gw]">Gateway</label>
					<div class="col-sm-10">
						<input class="form-control input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="gateway" name="nic[gw]" value="<?=$this->nic->gw ?>" placeholder="<?=$this->nic->gw ?>" data-parsley-trigger="change" required />
						<span class="help-block">Manually set the gateway.</span>
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label" for="nic[dns1]">Primary DNS</label>
					<div class="col-sm-10">
						<input class="form-control input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="dns1" name="nic[dns1]" value="<?=$this->nic->dns1 ?>" placeholder="<?=$this->nic->dns1 ?>" data-parsley-trigger="change" >
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label" for="nic[dns2]">Secondary DNS</label>
					<div class="col-sm-10">
						<input class="form-control input-lg" type="text" pattern="((^|\.)((25[0-5])|(2[0-4]\d)|(1\d\d)|([1-9]?\d))){4}$" id="dns2" name="nic[dns2]" value="<?=$this->nic->dns2 ?>" placeholder="<?=$this->nic->dns2 ?>" data-parsley-trigger="change" >
						<span class="help-block">Manually set the primary and secondary DNS.</span>
					</div>
				</div>
			</div>
		</fieldset>
		<div class="form-group form-actions">
			<div class="col-sm-offset-2 col-sm-10">
				<a href="/network" class="btn btn-default btn-lg">Cancel</a>
				<button type="submit" class="btn btn-primary btn-lg" name="save" value="save">Save and apply</button>
			</div>
		</div>
	</form>
</div>
<div id="net-config-defaults" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="mpd-config-defaults-label" aria-hidden="true">
		  <form name="netconf_reset" method="post" id="netconf_reset">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h3 id="mpd-config-defaults-label">Reset the configuration</h3>
			</div>
			<div class="modal-body">
				<p>You are going to reset the configuration to the default original values.<br>
				You will lose any modification.</p>
			</div>
			
			<div class="modal-footer">
			<input type="hidden" name="reset" value="1">
				<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
				<button type="submit" class="btn btn-primary" >Continue</button>
			</div>
		  </form>
</div>