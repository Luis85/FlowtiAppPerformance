<?php namespace ProcessWire;

class FlowtiModuleProfiler extends WireData implements Module {

	private $settings = false;
	private $pageRenderTime = 0;
	private $profileName = false;
	private $profile = false;

	public static function getModuleInfo() {
		return array(
			'title' => 'Flowti Profiler Service', 
			'version' => 11, 
			'summary' => 'Hooks into ProcessController to log Admin Modules',
			'href' => 'https://github.com/Luis85/FlowtiAppPerformance',
			'author' => 'Luis Mendez',
			'icon' => 'flask',
			'singular' => true, 
			'autoload' => 'template=admin', 
			'requires' => 'FlowtiAppPerformance',
		);
	}

	public function init() {

		$settings = $this->modules->getConfig('FlowtiAppPerformance');
		if(!$settings){
			$this->warning('Flowti Profiler is installed but not properly configured');
			return;
		}  
		$this->settings = $settings;
		$this->profileName = $this->createProfileName();

		$this->addHookBefore("Page::render", $this, 'startPageViewTimer');
		$this->addHookAfter("Page::render", $this, 'startPageViewTimer');

		$this->addHookBefore("ProcessController::execute", $this, 'startProfiler');
		$this->addHookAfter("ProcessController::execute", $this, 'startProfiler');
	}

	public function startPageViewTimer(HookEvent $event){
		$timer = 'PageRenderTime';
		if($event->when == 'before'){
			Debug::timer($timer); 
			return;
		}
		Debug::saveTimer($timer);
		$timer = Debug::getSavedTimer($timer)*1000;
		$this->pageRenderTime = $timer;
		if($this->settings['enabled'] || $this->settings['tracy']) $this->addRenderTime();
	}

    public function startProfiler(HookEvent $event){

		$pController = $event->object;
		$modules = $this->settings['modules'];
		$process = $event->object->getProcess();
		$class = $process->className();
		$method = $pController->getProcessMethodName($process);
		
		if(!in_array($class,$modules)) return;

		$timer = $class.'.'.$method;
		
		if($event->when == 'before'){
			Debug::timer($timer); 
			return;
		}		
		Debug::saveTimer($timer);
		$this->createProfile($timer, $class, $method);
	}

	private function createProfile($timer, $class, $method){

		$mem = memory_get_peak_usage();
		$mem = $mem/1000000;
		$load = sys_getloadavg();

		$profile['FlowtiPerformanceProfile'] = [
			'profile_name' => $this->profileName,
			'profile_class' => $class,
			'profile_method' => $method.'()',
			'profile_page' => $this->page->path(),
			'profile_memory_used' => $mem,
			'profile_exectime' => Debug::getSavedTimer($timer)*1000,
			'profile_page_render_time' => 0,
			'profile_avg_sysload' => floor($load[0]*10),
			'profile_user' => $this->user->id,
			'profile_timestamp' => time()
		];
		$this->profile = $profile['FlowtiPerformanceProfile'];
		if($this->settings['enabled'] == 0 && $this->settings['pwlogs'] == 0) return;
		$this->createLogentry($profile['FlowtiPerformanceProfile']);
	}

	private function createLogentry($profile){

		$logentry = [
			'title' => $profile['profile_name'],
			'flowti_performance_monitor_body' => json_encode($profile)
		];		

		if($this->settings['pwlogs'] == 1){
			$this->log->save('flowti-profiler', json_encode($profile));
		}

		if($this->settings['enabled'] == 0) return;

		$database = $this->pages->get('id='.$this->settings['logsparent']);
		$parent = $database->get('title='.$profile['profile_class']);
		
		if(!$parent->id){
			$parent = $this->pages->add('flowti-performance-monitor', $database , $profile['profile_class']);
		}
		if($parent->numChildren() >= $this->settings['maxlogs'] && $this->settings['maxlogs'] > 0 && $this->settings['continue'] == 1){
			$this->pages->delete($parent->children()->first());
		} 
		$this->pages->add('flowti-performance-monitor', $parent , $logentry);
	}

	private function createProfileName(){
		$name = time().md5(uniqid(rand(), true)).rand(1,1337);
		return $name;
	}

	private function addRenderTime(){

		$log = $this->pages->get('template=flowti-performance-monitor,name='.$this->profileName);

		if($log->id){
			$logBody = json_decode($log->flowti_performance_monitor_body, true);
			$logBody['profile_page_render_time'] = $this->pageRenderTime;
			$this->profile = $logBody;
			$logBody = json_encode($logBody);
			$log->setAndSave('flowti_performance_monitor_body', $logBody);
		} else {
			$this->profile['profile_page_render_time'] = $this->pageRenderTime;
		} 

		if($this->settings['tracy'] && $this->modules->isInstalled('TracyDebugger') && isset($this->profile['profile_name'])){
			bd($this->profile);
		}
		
	}

	public function ___install() {
		
		$field = $this->fields->get('flowti_performance_monitor_body');
		if(!isset($field->id)) {
			$field = new Field;
			$field->type = $this->modules->get('FieldtypeText');
			$field->name = 'flowti_performance_monitor_body';
			$field->label = $this->_("Log item content");
			$field->save();
		}
		$fg = $this->fieldgroups->get('flowti-performance-monitor');
		if(!isset($fg->id)){
			$fg = new Fieldgroup();
			$fg->name = 'flowti-performance-monitor';
			$fg->save();
			$fg->add($this->fields->get('title'));
			$fg->add($this->fields->get('flowti_performance_monitor_body'));
			$fg->save();
		}
		$template = $this->templates->get('flowti-performance-monitor');
		if(!isset($template->id)){
			$t = new Template();
			$t->name = 'flowti-performance-monitor';
			$t->fieldgroup = $fg;
			$t->save();
		}
 
	 }
 
	public function ___uninstall() {

		$logs = $this->pages->find('include=all,template=flowti-performance-monitor');
		foreach($logs as $log){
			$this->pages->delete($log,true);
		}

		$field = $this->fields->get('flowti_performance_monitor_body');
		$template = $this->templates->get('flowti-performance-monitor');
		$fieldgroup = $template->fieldgroup;
 
		if($template->id){
			$this->templates->delete($template);
			$this->fieldgroups->delete($fieldgroup);
		}
		if($field->id) $this->fields->delete($field);
	}
}