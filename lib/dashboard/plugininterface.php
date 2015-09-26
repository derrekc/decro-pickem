<?php 

namespace Dashboard;

interface PluginInterface {
	public function doHook($hook_name);	
}
