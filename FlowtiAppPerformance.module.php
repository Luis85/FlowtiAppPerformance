<?php namespace ProcessWire;

class FlowtiAppPerformance extends Process implements Module, ConfigurableModule{

    protected $data = array();
    private $profiles = array();    
    private $allData = array();

    static public function getDefaultData() {
		return array(
            'enabled' => 1,
			'modules' => array(),
            'maxlogs' => 50,
            'pwlogs' => 0,
            'tracy' => 1,
            'configured' => 0,
            'continue' => 1,
            'logsparent' => false,
		);
	}
	public function __construct() {        
		foreach(self::getDefaultData() as $key => $value) {
				$this->$key = $value;
		}
	}
	public function getModuleConfigInputfields(array $data){
		
		$data = array_merge(self::getDefaultData(), $data);
        
        $modules = wire('modules')->findByInfo('id=*');

        $wrapper = new InputfieldWrapper();
        
        $fieldset = new InputfieldWrapper();
        $fieldset->columnWidth = 100;
        $f = wire('modules')->get('InputfieldToggle');
        $f->attr('name', 'enabled');
        $f->label = 'Log handling';
        $f->val($data['enabled']);
        $f->description = 'Should the profiler save his data to the database?';
        $f->columnWidth = 33;
        if($this->modules->isInstalled('TracyDebugger')) $f->columnWidth = 25;        
        $fieldset->add($f);

        $f = wire('modules')->get('InputfieldToggle');
        $f->attr('name', 'pwlogs');
        $f->label = 'PW Log handling';
        $f->val($data['pwlogs']);
        $f->showIf = "enabled=0";
        $f->description = 'Should the profiler save his data to PW logs?';
        $f->columnWidth = 33;
        if($this->modules->isInstalled('TracyDebugger')) $f->columnWidth = 25;        
        $fieldset->add($f);

        $f = wire('modules')->get('InputfieldToggle');
        $f->attr('name', 'continue');
        $f->label = 'MaxLog handling';
        $f->val($data['continue']);
        $f->description = 'Should the profiler override the oldest log?';
        $f->showIf = "enabled=1,maxlogs!=0";
        $f->columnWidth = 33;
        if($this->modules->isInstalled('TracyDebugger')) $f->columnWidth = 25;        
        $fieldset->add($f);

        if($this->modules->isInstalled('TracyDebugger')){
            $f = wire('modules')->get('InputfieldToggle');
            $f->attr('name', 'tracy');
            $f->label = 'Enable Tracy Dumps';
            $f->val($data['tracy']);
            $f->description = 'Should the profiler dump his data to Tracy?';
            $f->columnWidth = 25;
            $fieldset->add($f);
        }

        $f = wire('modules')->get("InputfieldRadios");
        $f->attr('name', 'maxlogs');
        $f->label = 'Maximum Logs';
        $f->optionColumns = 1;
        $f->showIf = "enabled=1";
        $f->description = 'How many logs should we get for each Module?';
        $f->value = $data['maxlogs'];
        $f->addOption(50, 50);
        $f->addOption(100, 100);
        $f->addOption(200, 200);
        $f->addOption(0, 'No limit');
        $f->columnWidth = 50;
        $fieldset->add($f);

        $f = wire('modules')->get("InputfieldPageListSelect");
        $f->attr('name', 'logsparent');
        $f->label = 'Logs Folder';
        $f->showIf = "enabled=1";
        $f->optionColumns = 1;
        $f->description = 'Where should logs get saved? (Default Parent is Flowti Monitor)';
        $f->value = $this->pages->get('name=flowti-performance-monitor')->id;
        $f->columnWidth = 50;
        $fieldset->add($f);
        $wrapper->add($fieldset);

        $fieldset = new InputfieldWrapper();
        $fieldset->columnWidth = 100;
        $f = wire('modules')->get("InputfieldCheckboxes");
        $f->attr('name', 'modules');
        $f->label = 'Which Modules should be tracked?';
        $f->description = 'Every Module will create his own logs. Which means if you select 4 Modules and have a log limit of 50 you will get up to 200 new pages';
        $f->optionColumns = 3;
        foreach($modules as $module) {
            $info = wire('modules')->getModuleInfo($module, ['verbose' => true]);
            if(!$info['installed']) continue;
            if($info['core']) continue;
			$f->addOption($module, $module);
		}
        $f->columnWidth = 100;
        if($data['modules']) $f->attr('value', $data['modules']);
        $fieldset->add($f);

        $f = wire('modules')->get("InputfieldHidden");
        $f->attr('name', 'configured');
        $f->value = 1;
        $fieldset->add($f);

        $wrapper->add($fieldset);	

		return $wrapper;
	}	
    public function init(){

        $this->prepareData();
        
        $this->config->scripts->add('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js');
        $this->config->scripts->add('http://www.chartjs.org/samples/latest/utils.js');
        parent::init();
    }

    public function ___execute(){


        $logFolder = $this->pages->get('/app-performance/');

        $fieldset = $this->modules->get('InputfieldFieldset');
        $fieldset->collapsed = Inputfield::collapsedNever;

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'Monitoring';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = implode (',', $this->data['modules'] );
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'Logged Profiles '.$this->profiles->count;
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<a id="db-btn" class="uk-button uk-button-default uk-button-small" href="./database">View</a>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'AVG MEMORY USAGE MB';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<canvas id="avgmemory"></canvas>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'AVG EXECUTION TIME MS';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<canvas id="avgexectime"></canvas>';
        $field->columnWidth = 50;
        $fieldset->add($field);
        
        $field = $this->modules->get('InputfieldMarkup');
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<p class="uk-text-center">Peak '.$this->allData['sum']['memory']['peak'].' MB</p>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<p class="uk-text-center">Peak '.$this->allData['sum']['time']['peak'].' ms</p>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        return $fieldset->render();
    }
    public function ___executeDatabase(){

        return array(
            'table' => $this->renderTable()
        );
    }
    public function ___executeClearlogs(){
        $count = $this->profiles->count;
        foreach($this->profiles as $item){
            $this->pages->delete($item, true);
        }
        $this->message($count.' Profiles cleared');
        $this->session->redirect('./');
    }
    public function ___executeGetChartDataExecTime(){
        header("Content-Type: application/json;charset=utf-8");
        $response = array();
        foreach($this->allData['items'] as $k => $v){
            $response[] = ['label' => $k, 'val' => round($v['time']['avg'], 2)];
        }
        return json_encode($response);
    }
    public function ___executeGetChartDataMemory(){
        header("Content-Type: application/json;charset=utf-8");
        $response = array();
        foreach($this->allData['items'] as $k => $v){
            $response[] = ['label' => $k, 'val' => round($v['memory']['avg'], 2)];
        }
        return json_encode($response);
    }

    protected function ___renderTable(){
      
        $table = $this->modules->get('MarkupAdminDataTable');
        $table->headerRow(['Module', 'Method', 'Page', 'Memory', 'Time', 'SysLoad']);
    
        foreach($this->profiles as $item) {
            if($item->parent->template == 'admin') continue;
            $content = json_decode($item->flowti_performance_monitor_body);
            $table->row([
                $item->parent->title,
                $content->profile_method,
                $content->profile_page,
                $content->profile_memory_used.' MB',
                $content->profile_exectime.' ms',
                $content->profile_avg_sysload. '%'
            ]);
        }

        return $table->render();
    }
    protected function getAllData(){

        $averages = array();
        $averages['sum']['count'] = 0;
        $averages['sum']['memory'] = ['total' => 0 ,'avg' => 0, 'peak' => 0];
        $averages['sum']['time'] = ['total' => 0 ,'avg' => 0, 'peak' => 0];
        $averages['items'] = array();
    
        foreach($this->profiles as $item) {
            if(!$item->flowti_performance_monitor_body) continue;
            $title = (string)$item->parent->title;
            $item = json_decode($item->flowti_performance_monitor_body);

            $averages['sum']['count'] += 1;
            $averages['sum']['memory']['total'] += $item->profile_memory_used;
            $averages['sum']['time']['total'] += $item->profile_exectime;
            $averages['sum']['memory']['avg'] = $averages['sum']['memory']['total'] / $averages['sum']['count'];
            $averages['sum']['time']['avg'] = $averages['sum']['time']['total'] / $averages['sum']['count'];

            if($item->profile_exectime > $averages['sum']['time']['peak']) $averages['sum']['time']['peak'] = (float)$item->profile_exectime;
            if($item->profile_memory_used > $averages['sum']['memory']['peak']) $averages['sum']['memory']['peak'] = (float)$item->profile_memory_used;

            if(!isset($averages['items'][$title])){
                $averages['items'][$title]['count'] = 0;
                $averages['items'][$title]['memory'] = ['total' => 0 ,'avg' => 0, 'peak' => 0];
                $averages['items'][$title]['time'] = ['total' => 0 ,'avg' => 0, 'peak' => 0];
            }
            $averages['items'][$title]['count'] += 1;
            $averages['items'][$title]['memory']['total'] += $item->profile_memory_used;
            $averages['items'][$title]['time']['total'] += $item->profile_exectime;            
            $averages['items'][$title]['memory']['avg'] = $averages['items'][$title]['memory']['total'] / $averages['items'][$title]['count'];
            $averages['items'][$title]['time']['avg'] = $averages['items'][$title]['time']['total'] / $averages['items'][$title]['count'];

            if($item->profile_exectime > $averages['items'][$title]['time']['peak']) $averages['items'][$title]['time']['peak'] = (float)$item->profile_exectime;
            if($item->profile_memory_used > $averages['items'][$title]['memory']['peak']) $averages['items'][$title]['memory']['peak'] = (float)$item->profile_memory_used;

        }
        return $averages;
    }
    protected function prepareData(){

        $profiles = $this->pages->find("include=all,template=flowti-performance-monitor,sort=-sort");
        
        $this->profiles = $profiles;
        $this->allData = $this->getAllData();
    }

}