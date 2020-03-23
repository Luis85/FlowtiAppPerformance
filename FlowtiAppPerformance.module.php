<?php namespace ProcessWire;

class FlowtiAppPerformance extends Process implements Module, ConfigurableModule{

    protected $data = array();
    private $profiles = array();
    private $allData = array();
    private $categories = array();

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

        $this->config->scripts->add('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js');
        $this->config->scripts->add('https://www.chartjs.org/samples/latest/utils.js');
        parent::init();
    }

    public function ___execute(){
        
        $fieldset = $this->modules->get('InputfieldFieldset');
        $fieldset->collapsed = Inputfield::collapsedNever;

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'Monitoring';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = implode (',', $this->data['modules'] );
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'Logged Profiles '.$this->page->numDescendants('parent!='.$this->data['logsparent']);
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<a id="db-btn" class="uk-button uk-button-default uk-button-small" href="./database">View</a>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'AVG MEMORY USAGE MB';
        $field->id = 'avgmemorychart';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<canvas id="avgmemory"></canvas>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        $field = $this->modules->get('InputfieldMarkup');
        $field->label = 'AVG EXECUTION TIME MS';
        $field->id = 'avgexectimechart';
        $field->collapsed = Inputfield::collapsedNever;
        $field->value = '<canvas id="avgtime"></canvas>';
        $field->columnWidth = 50;
        $fieldset->add($field);

        return $fieldset->render();
    }
    public function ___executeDatabase(){
        $this->config->scripts->add('https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js'); 
        $this->config->styles->add('https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css');        
        return array(
            'tableModules' => $this->page->children(),
        );
    }
    public function ___executeClearlogs(){
        $profiles = $this->pages->findMany('include=all,template=flowti-performance-monitor');
        $count = $profiles->count;
        foreach($profiles as $item){
            $this->pages->delete($item, true);
        }
        $this->message($count.' Profiles cleared');
        $this->session->redirect('./');
    }

    //ajax endpoints
    public function ___executeGetAvgChartData(){
        header("Content-Type: application/json;charset=utf-8");
        if(!$this->input->get->data || !$this->config->ajax) return;
        $this->prepareData();
        $response = array();
        $dataset = $this->input->get->text('data');
        $dataset = str_replace('avg', '',$dataset);

        foreach($this->allData['items'] as $k => $v){
            $response[] = ['label' => $k, 'val' => round($v[$dataset]['avg'], 2)];
        }
        return json_encode($response);

    }
    public function ___executeGetDatatable(){
        header("Content-Type: application/json;charset=utf-8");
        if(!$this->config->ajax) return;
        $response = array();

        $pwfilter = 'parent!='.$this->data['logsparent'].',include=all,template=flowti-performance-monitor';
        $start = $this->input->post->int('start');

        $limit = 10;
        if($this->input->post->int('length') > 0) $limit = $this->input->post->int('length');
        
        $searchstring = $this->sanitizer->text($this->input->post->search['value']);
        $search = ',flowti_performance_monitor_body%='.$searchstring;

        $filter = false;
        if($this->input->get->filter) $filter = ',flowti_performance_monitor_body%='.$this->input->get->text('filter');
        
        $selector = ',start='.$start.',limit='.$limit;
        $sort = ',sort=sort';

        if(isset($this->input->post->order) && $this->input->post->order[0]['dir'] == 'desc') $sort = ',sort=-sort';
        
        $logs = $this->pages->findMany($pwfilter.$selector.$search.$sort.$filter);        

        $response = [
            'draw' => $this->input->post->int('draw'),
            'recordsTotal' => $logs->getTotal(),
            'recordsFiltered' => $logs->getTotal(),
            'start' => $logs->getStart(),
            'limit' => $logs->getLimit(),
            'search' => $search,
            'data' => array(),
            'avgMem' => 0,
            'avgTime' => 0,
            'avgLoad' => 0,
        ];

        $memory = 0;
        $time = 0;
        $load = 0;
        $counter = 0;
        foreach($logs as $log){
            $counter++;
            $title = (string)$log->parent->title;
            $log = json_decode($log->flowti_performance_monitor_body);
            $memory += $log->profile_memory_used; 
            $time += $log->profile_exectime;
            $load += $log->profile_avg_sysload;
            $response['data'][] = [
                'module' => '<span class="cell-filter">'.$title.'</span>', 
                'method' => '<span class="cell-filter">'.$log->profile_method.'</span>',
                'page' => $log->profile_page,
                'memory' => round($log->profile_memory_used,4),
                'time' => round($log->profile_exectime,4),
                'sysload' => $log->profile_avg_sysload.'%'
            ];
        }
        $response['avgMem'] = round($memory / $counter, 2);
        $response['avgTime'] = round($time / $counter, 2);
        $response['avgLoad'] = round($load / $counter, 2);
        
        return json_encode($response);
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
        $profiles = $this->pages->findMany('parent!='.$this->data['logsparent'].',include=all,template=flowti-performance-monitor,sort=-sort');
        $categories = $this->pages->find('include=all,parent='.$this->data['logsparent']);
        $this->categories = $categories;
        $this->profiles = $profiles;
        $this->allData = $this->getAllData();
    }

}