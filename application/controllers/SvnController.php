<?php

class SvnController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }
   public function getRemoteBasePath(){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$svn_path       = $options['svnrelay']['svn_path'];
	$svn_repository = $options['svnrelay']['svn_repository'];
	return $svn_path.$svn_repository;
   }
   public function getLocalBasePath(){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$svn_local      = $options['svnrelay']['svn_local'];
	$svn_repository = $options['svnrelay']['svn_repository'];
	return $svn_local.$svn_repository;
   }
   public function getDigfirUrl(){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	return $options['svnrelay']['digfir_url'];
   }
  //MT: gives recursive listing of files in remote repository using $path as url
   public function listRemote($path,$_base=''){
	$_array = array();
	$_ls = svn_ls($path);
	foreach($_ls as $_key=>$_value){
		if(array_key_exists('type',$_value)){
			if($_value['type']==='dir'){
				$_subpath = $_base.$_value['name'].'/';
				$_array[] = $_subpath;
				$_array   = array_merge($_array,$this->listRemote($path."/".$_value['name'],$_subpath));
			}else if($_value['type']==='file'){
				$_array[] = $_base.$_value['name'];
			}
		}
	}
	return $_array;
   }
   public function get_subdir_files($path,$_base='') {
	$_array = array();
    	$_ls    = scandir($path);
        foreach($_ls as $_f)  {
            if ($_f === '.' || $_f === '..' || $_f=='.svn') {
                continue; }
	    if(is_dir($_f)){
		$_subpath = $_base.$_f.'/';
		$_array[] = $_subpath;
            	$_array   = array_merge($_array,$this->get_subdir_files($path."/".$_f,$_subpath));
            }else{
		$_array[] = $_base.$_f;
	    }
    	}   
    	return $_array;
    }


   public function hasSvnLibraries(){
	return function_exists("svn_add");
   }

   public function indexAction(){
	//$this->getHelper('layout')->setLayout('ajax');
	//$this->getHelper('layout')->setLayout('default');
	if($this->hasSvnLibraries()){
		$this->view->has_svn = true;
		$this->view->svn_path_base_remote  = $this->getRemoteBasePath();//$svn_path.$svn_repository;
		$this->view->svn_path_base_local   = $this->getLocalBasePath();//$svn_local.$svn_repository;
	}else{
		$this->view->has_svn = false;
	}
   }
   public function lsAction(){
        $this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_ls = svn_ls($this->getRemoteBasePath());
	$this->view->message = "svn list successful";
   }
   public function listAction(){
	$this->subtype = $this->getRequest()->getParam('subtype');
 	$this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_list = $this->listRemote($this->getRemoteBasePath()."/".$this->subtype);
   }
   public function listlocalAction(){
	$this->subtype = $this->getRequest()->getParam('subtype');
 	$this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_list = $this->get_subdir_files($this->getLocalBasePath()."/".$this->subtype);
	
   }
   public function localworkingcopyexistsAction(){
	$this->subtype = $this->getRequest()->getParam('subtype');
	$this->getHelper('layout')->setLayout('ajax');
	$svn_path_base_local      = $this->getLocalBasePath();//$svn_local.$svn_repository;
	$svn_path_subtype_local   = $svn_path_base_local."/".$this->subtype;
	$this->view->localworkingcopyexists = file_exists($svn_path_subtype_local."/.svn")? "1" : "0";
	$this->view->message = $this->view->localworkingcopyexists? "local working copy exists" : "local working copy does not exist";
	$this->view->svn_path_subtype_local = $svn_path_subtype_local;
   }
   public function updateAction(){
  	$this->subtype = $this->getRequest()->getParam('subtype');
	$this->getHelper('layout')->setLayout('ajax');
	$svn_path_base_local      = $this->getLocalBasePath();//$svn_local.$svn_repository;
	$svn_path_subtype_local   = $svn_path_base_local."/".$subtype;
	$this->view->svn_path_subtype_local  = $svn_path_subtype_local;
	$this->view->svn_subtype_updated     = svn_update($this->view->svn_path_subtype_local);
	if($this->view->svn_subtype_updated){
		$this->view->message = "the local working copy was updated.";
	}else{
		$this->view->message = "the local working copy was not updated.";
	}
   }
   public function copypublishedfilesAction(){
  	$this->subtype = $this->getRequest()->getParam('subtype');
	$this->getHelper('layout')->setLayout('ajax');
	$svn_path_base_local      = $this->getLocalBasePath();//$svn_local.$svn_repository;
	$svn_path_subtype_local   = $svn_path_base_local."/".$subtype;
	$this->view->svn_path_subtype_local  = $svn_path_subtype_local;

	//MT: return OK for now, do nothing
	$this->view->message = "copypublishedfiles: OK";
   }

   public function statusAction(){
        $this->subtype = $this->getRequest()->getParam('subtype');
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_status = svn_status($this->getLocalBasePath()."/".$this->subtype);	  
   } 
   //MT: ajax method that copies files from publish directory to local working copy, compares files, and then commits to repository
   public function commitAction(){
	$this->getHelper('layout')->setLayout('ajax');
	$zip_contents_string = $this->getRequest()->getParam('body');
	try{
		$zip_contents_obj  = json_decode($zip_contents_string,TRUE);
		$base_dir          = $zip_contents_obj['base_dir'];
		$contents          = $zip_contents_obj['contents'];
		$this->view->message = "OK";	
	}catch(Exception $e){
		$this->view->message = "Error: ".$e;		
	}
   }

  

   //MT: test svn action to follow publish all
   //see http://www.php.net/manual/en/book.svn.php for svn libraries for php
   //these need to be installed on any machine for svn facilities to work
   public function testinfoAction(){
	$subtype = "bar";
	$this->view->subtype = $subtype;
	$id      = $this->getRequest()->getParam('id');
	$this->getHelper('layout')->setLayout('ajax');

	//if (!Ombu::user()->canAccessManuscript($id)) {
            //$this->getHelper('redirector')->goto('list', 'manuscript');
        //}


	//$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	//$options        = $bootstrap->getOptions();
	//$svn_path       = $options['digfir']['svn_path'];
	//$svn_repository = $options['digfir']['svn_repository'];
	//$svn_local      = $options['digfir']['svn_local'];
	
	//$this->view->svn_path  = $svn_path;
	//$this->view->svn_repos = $svn_repository;
	//$this->view->svn_local = $svn_local;

	
	$svn_path_base_remote  = $this->getRemoteBasePath();//$svn_path.$svn_repository;
	$svn_path_base_local   = $this->getLocalBasePath();//$svn_local.$svn_repository;

	
	$svn_path_subtype_remote  = $svn_path_base_remote."/".$subtype;
	$svn_path_subtype_local   = $svn_path_base_local."/".$subtype;

	$this->view->svn_path_base_remote    = $svn_path_base_remote;
	$this->view->svn_path_base_local     = $svn_path_base_local;

	$this->view->svn_path_subtype_remote = $svn_path_subtype_remote;
	$this->view->svn_path_subtype_local  = $svn_path_subtype_local;

	if(function_exists("svn_add")){
		$this->view->message = "php svn libraries installed";
		$this->view->svn_ls    = svn_ls($svn_path_base_remote);
		//$this->view->svn_list  = $this->listRemote($svn_path_base_remote);
		if(!file_exists($svn_path_base_local."/.svn")){
			$this->view->svn_base_checkedout = svn_checkout($svn_path_base_remote,$svn_path_base_local);
		}else{
			//MT: update entire digfir repository
			//for now just update subtype repository (see below) not whole thing
			//$this->view->svn_base_updated    = svn_update($svn_path_base_local);
		}
		$this->view->svn_subtype_exists_remote = array_key_exists($subtype,$this->view->svn_ls);
		$this->view->svn_subtype_exists_local  = file_exists($svn_path_subtype_local."/.svn");
		
		if($this->view->svn_subtype_exists_remote){
			//MT: remote repository directory exists for subtype.
			//update subtype repository only
			$this->view->svn_subtype_updated    = svn_update($svn_path_subtype_local);
			if($this->view->svn_subtype_updated){
				$this->view->svn_subtype_list       = $this->listRemote($svn_path_subtype_remote);
			}
		}else{
			//MT: subtype does not exist in remote repository
			//MT: first check to see if local working copy dir exists
			if(file_exists($svn_path_subtype_local)){
				//delete working copy dir---for error failover in case dir has been created by accident without checkin
				//note: must actually do this recursively just in case
				//just delete dir for now
				rmdir($svn_path_subtype_local);
			}
			//MT: this condition should always be true if dir was deleted as per prev step---leave in for now
			if(!file_exists($svn_path_subtype_local)){
				//create local working copy directory
				//check in to remote repository
				$this->view->svn_subtype_mkdir     = svn_mkdir($svn_path_subtype_local);
				$this->view->svn_subtype_committed = svn_commit("created subtype ".$subtype,array($svn_path_subtype_local));
			}
		}
			
	}else{
		$this->view->message = "php svn libraries not installed";
	}
   }

   function delete_directory($dirname) {
			if (is_dir($dirname)) {
				$dir_handle = opendir($dirname);
			}
			if (!$dir_handle) {
				return false;
			}
			while($file = readdir($dir_handle)) {
					if ($file != "." && $file != "..") {
						if (!is_dir($dirname."/".$file)) {
							unlink($dirname."/".$file);
						} else {
							delete_directory($dirname.'/'.$file);    
						}
					}
			}
			closedir($dir_handle);
			rmdir($dirname);
			return true;
   }


    public function testAction()
    {
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->subtype = $this->getRequest()->getParam('subtype');
        // action body
    }
    public function initiatecommitAction(){
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->subtype = $this->getRequest()->getParam('subtype');
	error_log("subtype=".$this->view->subtype);
	$this->view->testmessage = "OK";
	if(!$this->view->subtype){
		$this->view->message = "ERROR: subtype not defined";
	}else{
		$this->view->message = "OK";
	}	
    }
    public function downloadzipAction(){
	$this->doDownloadZip('alpha');
    }
    public function doDownloadZip($subtype){
	$digfir_url	= $this->getDigfirUrl();
	$url 		= $digfir_url.'subtype/downloadzip/id/'.$subtype.".zip";
	$client   = new Zend_Http_Client($url, array(
    			'maxredirects' => 5,
    			'timeout'      => 30));
	$response = $client->request('GET');
	$body     = $response->getBody();
	error_log($body);
	$ctype 	  = $response->getHeader('Content-type');
	if (is_array($ctype)) $ctype = $ctype[0];
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->message = "OK";
	$this->view->subtype = $subtype;
	$this->view->url     = $url;
	$this->view->ctype   = $ctype;

    }
}
?>
