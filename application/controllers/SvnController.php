<?php

class SvnController extends Zend_Controller_Action
{

  

    public function init()
    {
        /* Initialize action controller here */
    }
   public function updateRemoteFiles(){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	return $options['svnrelay']['update_remote_files'];
   }
  
   public function getRemoteBasePath($subtype=null){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$svn_path       = $options['svnrelay']['svn_path'];
	$svn_repository = $options['svnrelay']['svn_repository'];
	$_path = $svn_path.$svn_repository;
	if($subtype){
		$_path = $_path .= "/".$subtype;
	}
	return $_path;
   }
   public function getLocalBasePath($subtype=null){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$svn_local      = $options['svnrelay']['svn_local'];
	$svn_repository = $options['svnrelay']['svn_repository'];
	$_path = $svn_local.$svn_repository;
	if($subtype){
		$_path = $_path .= "/".$subtype;
	}
	return $_path;
   }
   public function getDigfirfilesUrl(){
	$bootstrap 	= Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	if(!array_key_exists('digfirfiles_url',$options['svnrelay'])){
		return null;
	}
	$digfirfiles_url     =   $options['svnrelay']['digfirfiles_url'];
	return $digfirfiles_url;
   }
   public function getDigfirfilesPublicUrl($subtype){
	$_base_url = $this->getDigfirfilesUrl();
	$_public_url = $_base_url."files/";
	if($subtype){
		$_public_url = $_public_url . $subtype."/";
	}
	return $_public_url;
	
   }
   public function getDigfirfilesUpdateUrl($subtype=null){
	$digfirfiles_url = $this->getDigfirfilesUrl();
	if($digfirfiles_url ==null) return null;
	$digfirfiles_update_url     = $digfirfiles_url."svn/update";

	if($subtype){
		$digfirfiles_update_url    = $digfirfiles_update_url     . "/subtype/".$subtype;
	}
	return $digfirfiles_update_url;
   }
   public function getDigfirUrl(){
	//$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	//$options        = $bootstrap->getOptions();
	//return $options['svnrelay']['digfir_url'];
	$this->view->digfirhost = $this->getRequest()->getParam('digfirhost');
	$digfir_url = "http://".$this->view->digfirhost."/";
	error_log("using digfir url ".$digfir_url);
	return $digfir_url;
   } 
   public function getDownloadDir($subtype=null){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$_dir           = $options['svnrelay']['download_dir'];
	if($subtype){
		$_dir .= "/".$subtype;
	}
	return $_dir;
   }
   public function updateLocalWorkingCopy($subtype){
	$svn_path_subtype_local 	= $this->getLocalBasePath($subtype);
	$svn_subtype_current_version    = svn_update($svn_path_subtype_local);
        $svn_subtype_new_version        = $svn_subtype_current_version;
	$svn_subtype_updated            = 0;
	$svn_update_error               = 0;
	$svn_subtype_committed          = null;
	$_commited			= false;
	$files_deleted			= array();
	$files_added			= array();

	//MT: this must be set to TRUE in order to avoid conflict errors for commits involving both deletions and additions/modifications
	$_do_separate_commit_for_deletions = TRUE;

	error_log("svn update found current version of ".$subtype." to be ".$svn_subtype_current_version);

	$_extracted 		= $this->getDownloadDir($subtype);
	error_log("updating working copy at ".$svn_path_subtype_local);
	if(file_exists($_extracted)){
		if($svn_subtype_current_version){
			
		
			//MT: pseudocode for commit of existing subtype files
			//get list of files in existing working copy
			//get list of files in extracted directory
			//do comparison to find new files to add, old files to delete
			//copy files from old to new, forcing the write for changed files
			
			$_files_to_add  		= NULL;
			$_files_to_delete 		= NULL;
			error_log("comparing local working copy to extracted dir ".$_extracted);
			$_diff 		= $this->compare_directories($_extracted,$svn_path_subtype_local);
			$this->view->diff = $_diff;
			if($_diff){
				$_files_to_add  		= $_diff[0];
				$_files_to_delete	 	= $_diff[1];
				error_log("num files to add: ".count($_files_to_add));
				error_log("num files to delete: ".count($_files_to_delete));
			}
			
			
			if($_files_to_delete){
				$_deleted = 0;
				foreach($_files_to_delete as $_f){
					$_delete_path = $svn_path_subtype_local."/".$_f;
					
					$_deleted = @svn_delete($_delete_path,TRUE);
					error_log("svn delete ".$_delete_path." ".$_deleted);
					$files_deleted[] = $_f;
				}
				if($_deleted && $_do_separate_commit_for_deletions){
					//MT: need to do this commit after files deleted to avoid conflicts that arise from deletions
					//when deletions are also accompanied by additions and/or modifications
					//such conflicts result in statuses in form
					// !     C deleted_file.html
     					// 	 >   local delete, incoming delete upon update
					// see http://triopter.com/archive/resolving-local-delete-incoming-delete-upon-update-subversion-tree-conflicts/
					// must be resolved on command line by
					// $ touch deleted_file.html
					// $ svn revert deleted_file.html
					// $ rm deleted_file.html
					// which results in a cleared status
					error_log("svn commiting deleted files for ".$svn_path_subtype_local);
					$svn_subtype_committed  = @svn_commit("commiting subtype ".$subtype." for deleted files.",array($svn_path_subtype_local));
					$_commited		= true;
				}
			}
			//MT: this must come before the add
			$this->view->copied    = $this->copy_directory($_extracted,$svn_path_subtype_local);
			//MT: must come last after delete, copy
			if($_files_to_add){
				foreach($_files_to_add as $_f){
					$_add_path = $svn_path_subtype_local."/".$_f;
					error_log("svn add ".$_add_path);
					@svn_add($_add_path);
					$files_added[] = $_f;
				}
			}
			

			
			error_log("svn status ".$svn_path_subtype_local);
			$this->view->status = @svn_status($svn_path_subtype_local);
			if(is_array($this->view->status)){
				$_status_size       = count($this->view->status);
				error_log("svn status found ".$_status_size." items.");
				//foreach($this->view->status as $_status_item){
										

				//}				
		
				if($_status_size>0){

					error_log("svn commiting added and modified files for ".$svn_path_subtype_local);

					//MT: error suppression from this error which may be due to a bug in the API
					//https://bugs.php.net/bug.php?id=60583
					/**
			[Thu May 24 17:06:20 2012] [error] [client 127.0.0.1] PHP Warning:  svn_commit(): svn error(s) occured\n200031 (Attempted to write to readonly SQLite db) Commit failed (details follow):\n200031 (Attempted to write to readonly SQLite db) attempt to write a readonly database\n200031 (Attempted to write to readonly SQLite db) attempt to write a readonly database\n in /var/www/bfw-svnrelay/application/controllers/SvnController.php on line 89
			NOTE file is added correctly but generates error on commit, but then shows up in repository
					**/

					$svn_subtype_committed  = @svn_commit("commiting updated subtype ".$subtype,array($svn_path_subtype_local));
					$_commited		= true;
					//$this->view->committed = $svn_subtype_committed;

					


				}else{
					error_log("svn not commiting ".$subtype." because no changes to files were found.");
				}
			}else{
				error_log("svn found null status for ".$subtype);
			}
		}
	}
	if($_commited){
			//This generates an 'E' status on the command line, but seems to make things work
			$svn_subtype_new_version    = @svn_update($svn_path_subtype_local);
			$svn_subtype_updated        = $svn_subtype_new_version > $svn_subtype_current_version ? "1" : "0";
			error_log("svn repository for ".$subtype." after commit now at version ".$svn_subtype_new_version);
			//return $svn_subtype_committed[0];
					
			$this->view->statusafter = @svn_status($svn_path_subtype_local);
					if(is_array($this->view->statusafter)){
						if(count($this->view->statusafter)>0){
							$svn_update_error = 1;//count($this->view->statusafter);
							error_log("ERROR: status for ".$subtype." unresolved after update:");
							error_log(json_encode($this->view->statusafter));
						}
					}
	}
	$this->view->filesadded   = $files_added;
	$this->view->filesdeleted = $files_deleted;
	return array($svn_subtype_updated, $svn_subtype_current_version, $svn_subtype_new_version,$svn_update_error);
   }
   public function createRemoteRepository($subtype,$fromextracted=true){
	$svn_subtype_committed  = null;
	$svn_path_subtype_local = $this->getLocalBasePath($subtype);
	//MT: subtype does not exist in remote repository
	//MT: first check to see if local working copy dir exists
	if(file_exists($svn_path_subtype_local)){
		//delete working copy dir---for error failover in case dir has been created by accident without checkin
		$this->delete_directory($svn_path_subtype_local);
	}
	//MT: this condition should always be true if dir was deleted as per prev step---leave in for now
	if(!file_exists($svn_path_subtype_local)){
		//create local working copy directory
		//check in to remote repository
		
		if($fromextracted){
			$_extracted = $this->getDownloadDir($subtype);
			if(file_exists($_extracted)){
				//$svn_subtype_mkdir     = svn_mkdir($svn_path_subtype_local);
				$this->view->copied    = $this->copy_directory($_extracted,$svn_path_subtype_local);
				$_added                = svn_add($svn_path_subtype_local);
			}
		}else if(!file_exists($svn_path_subtype_local)){
			$svn_subtype_mkdir     = svn_mkdir($svn_path_subtype_local);
		}
		$svn_subtype_committed = @svn_commit("created subtype ".$subtype,array($svn_path_subtype_local));
	}
	return $svn_subtype_committed[0];
   }


  //MT: gives recursive listing of files in remote repository using $path as url
   public function listRemote($path,$_base=''){
	error_log("listRemote path=".$path);
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
	//error_log("path: ".$path);
	$_array = array();
    	$_ls    = scandir($path);
        foreach($_ls as $_f)  {
            if ($_f === '.' || $_f === '..' || $_f=='.svn') {
                continue; }
	    if(is_dir($path."/".$_f)){
		//error_log("dir: ".$_f);
		$_subpath = $_base.$_f.'/';
		$_array[] = $_subpath;
            	$_array   = array_merge($_array,$this->get_subdir_files($path."/".$_f,$_subpath));
            }else{
		//error_log("file: ".$_f);
		$_array[] = $_base.$_f;
	    }
    	}   
    	return $_array;
    }
    public function copy_directory($path1,$path2){
	$_copied = NULL;
	if(file_exists($path1)){
		$_copied = array();
		if(!file_exists($path2)){
			//$_copied[$path2] = @mkdir($path2);
			@mkdir($path2);
		}else{
			//$_copied[$path2] = TRUE;
		}
		if(file_exists($path2) && is_writable($path2)){
			$_ls = $this->get_subdir_files($path1);
			foreach($_ls as $_f){
				$_spath = $path1."/".$_f;
				$_dpath = $path2."/".$_f;
				if(is_dir($_spath)){
					if(!file_exists($_dpath)){
						$_copied[$_f] = @mkdir($_dpath);
					}else{
						$_copied[$_f] = TRUE;
					}
				}else{	
					$_copied[$_f] = @copy($_spath,$_dpath);	
				}
			}	
		}
	}
	return $_copied;
    }
   public function compare_directories($path1,$path2){
	
	if(file_exists($path1) && file_exists($path2)){
		$_e_1  = array();
		$_e_2  = array();

		$_ls_1 = $this->get_subdir_files($path1);
		$_ls_2 = $this->get_subdir_files($path2);

		return array(array_diff($_ls_1,$_ls_2),array_diff($_ls_2,$_ls_1));

	}
	return NULL;

   }

   public function hasSvnLibraries(){
	return function_exists("svn_add");
   }
   public function lsRemote(){
	$bootstrap 	= Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	//return $options['svnrelay']['digfir_url'];
	$_username = $options['svnrelay']['svn_auth_username'];
	$_password = $options['svnrelay']['svn_auth_password'];
	if($_username){
		svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $_username);
		svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $_password);
	}
	return svn_ls($this->getRemoteBasePath());
   }
   public function repositoryExists($name){
	$_ls = $this->lsRemote();
	$this->view->ls = $_ls;
	return array_key_exists($name,$_ls);
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
   public function infoAction(){

	//$this->do_repository = 1;//$this->getRequest()->getParam('dorepository');
	
	$this->do_repository = 1;
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_path_base_remote  = $this->getRemoteBasePath();//$svn_path.$svn_repository;
	$this->view->svn_path_base_local   = $this->getLocalBasePath();//$svn_local.$svn_repository;
	$this->view->svnupdater_url	   = $this->getUpdaterUrl();
	
	if($this->hasSvnLibraries()){
		$this->message       = "OK";
		$this->view->has_svn = true;
	
		//error_log(json_encode($this->lsRemote(),TRUE));
		if($this->do_repository){
			$this->view->svn_ls =json_encode($this->lsRemote(),TRUE);
		}
		//$this->view->svn_ls = "1";
	}else{
		$this->view->has_svn = false;
		$this->view->message = "php svn libraries not installed";
	}
	
   }
   public function lsAction(){
        $this->getHelper('layout')->setLayout('ajax');
	$this->view->svn_ls = $this->lsRemote();
	$this->view->message = "svn list successful";
   }
   public function deleteAction(){
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->subtype 		   = $this->getRequest()->getParam('subtype');
	$this->view->svn_path_base_local   = $this->getLocalBasePath($this->view->subtype);
	$svn_delete	   		   = svn_delete($this->view->svn_path_base_local,TRUE);
	if($svn_delete){
		$this->view->svn_committed         = svn_commit("deleting subtype ".$this->view->subtype,array($this->view->svn_path_base_local));
		$this->view->message = "svn deleted successful";
		$this->view->deleted = $this->view->svn_committed[0];
	}else{
		$this->view->svn_committed         = 0;
		$this->view->message = "svn not deleted";
		$this->view->deleted = "0";
	}
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
   function list_directory($dirname){
	$_array = array();
	/**
	
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
    	
	**/
	return $_array;
   }

   function delete_directory($dirname, $result = true) {
			if (is_dir($dirname)) {
				$dir_handle = opendir($dirname);
			}
			if (!$dir_handle) {
				return false;
			}
			while($file = readdir($dir_handle)) {
					if ($file != "." && $file != "..") {
						if (!is_dir($dirname."/".$file)) {
							$result = unlink($dirname."/".$file) && $result;
						} else {
							$result = $this->delete_directory($dirname.'/'.$file, $result) && $result;    
						}
					}
			}
			closedir($dir_handle);
			$result = rmdir($dirname) && $result;
			return $result;
   }


    public function testAction()
    {
	error_log("testAction called");
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
	
	error_log("======");
	$subtype    = $this->getRequest()->getParam('subtype');
	$digfir     = $this->getRequest()->getParam('digfir');
	
	
	$this->view->digfir 	= $digfir;
	if($digfir){
		error_log("processing download request from digfir...");

	}
	if($subtype){
		
		$this->view->success = $this->doDownloadZip($subtype);
	}else{
		$this->view->message = "The subtype must be specified.";
	}
    }
    //MT: performs login to DIGFIR application
    public function doRemoteLogin(){
	$digfir_url	= $this->getDigfirUrl();
	$url 		= $digfir_url.'user/login';
	$client   = new Zend_Http_Client($url, array(
    			'maxredirects' => 5,
			'keepalive' => true,
    			'timeout'      => 30));
	$client->setConfig(array('strictredirects' => false));
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$digfir_user_email       = $options['svnrelay']['digfir_user_email'];
	$digfir_user_password    = $options['svnrelay']['digfir_user_password'];
	$client->setCookieJar();	
	$client->setParameterPost('email',       $digfir_user_email);
	$client->setParameterPost('password',    $digfir_user_password);
	$response  = $client->request("POST");
	$body     = $response->getBody();
 	$invalid  = strpos($body,"Invalid email or password");
	$this->view->message  = $invalid ? "login unsuccesful" : "login successful";
	error_log("logging into DIGFIR with (".$digfir_user_email.",".$digfir_user_password).")";
	error_log("message: ".$response->getStatus()." ".$response->getMessage());
	$headers = $response->getHeaders();
	foreach($headers as $name=>$value){
	//error_log("  ".$name.": ".$value);
	}
	error_log("result: ".$this->view->message);
	return $client;
    } 

    public function doDownloadZip($subtype){
	
	$client   = $this->doRemoteLogin();
	$retarray = array();

	$download_dir = $this->getDownloadDir();
	$download_file = $download_dir."/".$subtype.".zip";

	if(file_exists($download_file)){
		unlink($download_file);
		error_log("deleting previous zip file ".$download_file);
	}

	$extract_dir = $download_dir."/".$subtype;
	
	if(file_exists($extract_dir)){
		error_log("deleting directory ".$extract_dir);
		$this->delete_directory($extract_dir);
			
	}

	$digfir_url	= $this->getDigfirUrl();



	$url 		= $digfir_url.'subtype/downloadzip/id/'.$subtype.".zip";
	//$url            = $digfir_url.'manuscript/test/';
	$client->setUri($url);
	$client->setStream();
	$response = $client->request('GET');
	$body     = $response->getBody();
	//error_log($body);
	//error_log($response->getMessage());
	//error_log("retrieving zip file from ".$url);
	
	$headers = $response->getHeaders();
	foreach($headers as $name=>$value){
	//error_log("  ".$name.": ".$value);
	}
	$ctype 	  = $response->getHeader('Content-type');
	//error_log("message: ".$response->getStatus()." ".$response->getMessage()." content-type:".$ctype);
	//error_log("stream name: ".$response->getStreamName());

	
	//error_log("file name: ".$download_file);
	$this->view->copied = copy($response->getStreamName(),$download_file);
	error_log("copied zip file to ".$download_file);
	$zip = new ZipArchive;
	$res = $zip->open($download_file);
	if ($res === TRUE) {
		
		error_log("creating directory ".$extract_dir);
		mkdir($extract_dir);
		
         	$zip->extractTo($extract_dir."/");
         	$zip->close();
         	$this->view->extracted = 1;

		$_dir_listing = $this->get_subdir_files($extract_dir);
		foreach($_dir_listing as $_listing){
			//error_log($_listing);
		}
		$this->view->dirlisting = $_dir_listing;
     	} else {
         	$this->view->extracted = 0;
		error_log("zip extract error: ".$res." for ".$download_file);
     	}
	try{
		$this->view->remoteexists = $this->repositoryExists($subtype);
		if(!$this->view->remoteexists){
			$this->view->remotecreated = $this->createRemoteRepository($subtype);	
		}else{
			 
			$_update_result= $this->updateLocalWorkingCopy($subtype);
			$this->view->localupdated  = $_update_result[0];
			$this->view->oldversion    = $_update_result[1];
			$this->view->newversion    = $_update_result[2];
			$this->view->updateerror   = $_update_result[3];
			
		}
		
	}catch(Exception $e){
		error_log($e);
	}



	if (is_array($ctype)) $ctype = $ctype[0];
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->message  = $this->view->updateerror ? "ERROR" : "OK";
	$this->view->subtype  = $subtype;
	$this->view->url      = $url;
	$this->view->ctype    = $ctype;
	$this->view->filename = $download_file;

	$retarray['message']          = $this->view->message;
	$retarray['subtype']          = $this->view->subtype;
	$retarray['url']              = $this->view->url;
	$retarray['ctype']            = $this->view->ctype;
	$retarray['filename']         = $this->view->filename;
	
	$retarray['extracted']        = $this->view->extracted;
	$retarray['remotecreated']    = $this->view->remotecreated ? $this->view->remotecreated : 0;
	$retarray['localupdated']     = $this->view->localupdated;
	$retarray['oldversion']       = $this->view->oldversion;
	$retarray['newversion']       = $this->view->newversion;
	$retarray['copied']           = $this->view->copied;
	$retarray['diff']	      = $this->view->diff;
	$retarray['committed']	      = $this->view->committed;
	$retarray['filesadded']       = $this->view->filesadded;
	$retarray['filesdeleted']     = $this->view->filesdeleted;
	$retarray['statusafter']      = $this->view->statusafter;

	
	
	if($this->updateRemoteFiles()){
		if($this->view->remotecreated || $this->view->localupdated){
			$remoteupdated = $this->promptRemoteUpdate($subtype);	
			if($remoteupdated){
				$retarray['remoteupdated']   = json_decode($remoteupdated);
			
			}
		}
		$retarray['digfirfiles_url'] = $this->getDigfirfilesPublicUrl($subtype);
	}
	$this->view->msg =	json_encode($retarray);
	return 1;


    }
    function promptRemoteUpdate($subtype){
	$digfirfiles_update_url = $this->getDigfirfilesUpdateUrl($subtype);

	if($digfirfiles_update_url){
			error_log("calling digfirfiles update with url ".$digfirfiles_update_url);
			$client   = new Zend_Http_Client($digfirfiles_update_url, array(
    			'maxredirects' => 5,
			'keepalive' => true,
    			'timeout'      => 30));
			
			$response = $client->request('POST');
			$body     = $response->getBody();
			error_log("digfirfiles update returned body ".$body);
			return $body;

			

			
	}else{
			error_log("not calling digfirfiles update because url was null");
	}
	return null;
    }
    function digfirfilesAction(){
	$_subtype        = $this->getRequest()->getParam('subtype');
	$_remoteaction   = $this->getRequest()->getParam('remoteaction');

	$_url    =  $this->getDigfirfilesUrl();
	$_url    =  $_url.'svn';
	if(!$_remoteaction){
		$_remoteaction = "ping";
	}
	$_url  = $_url.'/'.$_remoteaction;
	
	if($_subtype){
		$_url = $_url.'/subtype/'.$_subtype;
	}
	//error_log("digfirfiles url: ".$_url);

	$client   = new Zend_Http_Client($_url, array(
    			'maxredirects' => 5,
    			'timeout'      => 30));
	$response = $client->request('GET');
        $this->getHelper('layout')->setLayout('ajax');
	$this->view->jsonstring = $response->getBody();
    }
    function checkoutAction(){
	$this->getHelper('layout')->setLayout('ajax');
	$local_path 		   = "/home/matt/svn/digfir.test.0";
	$local_path_container      = $local_path."/container";
	error_log("OK: checkoutAction reinitializing working copy at ".$local_path);

	$retarray = array();
	$retarray['message'] = 'OK';

	if(file_exists($local_path)){
		//local path exists
		error_log("OK: checkoutAction local path ".$local_path." exists.");
		if(is_writable($local_path)){
			error_log("OK: checkoutAction local path ".$local_path." is writable.");
			$created_local_container = 0;
			if(file_exists($local_path_container)){
				//container exists, so delete it
				error_log("OK: checkoutAction local container path ".$local_path_container." exists.");
				if(is_writable($local_path_container)){
					error_log("OK: checkoutAction local container path ".$local_path_container." is writable.");
					$deleted = $this->delete_directory($local_path_container);
					error_log("OK: checkoutAction deleted directory ".$local_path_container. ": ".$deleted);
					if(mkdir($local_path_container)){
						$created_local_container = 1;
						error_log("OK: checkoutAction creating directory ".$local_path_container);
						
					}else{
						error_log("ERROR: checkoutAction unable to create ".$local_path_container." is not writable.");
					}
					
				}else{
					error_log("ERROR: checkoutAction local container path ".$local_path_container." is not writable.");
					//error: container is not writable
				}
			}else{
				error_log("OK: checkoutAction local container path ".$local_path_container." does not exist.");
				if(mkdir($local_path_container)){
						$created_local_container = 0;
						error_log("OK: checkoutAction creating directory ".$local_path_container);
				}else{
						error_log("ERROR: checkoutAction unable to create ".$local_path_container." is not writable.");
				}
			}
			if($created_local_container){
				$repository_path = $this->getRemoteBasePath();
				$checked_out = svn_checkout($repository_path,$local_path_container);
				if($checked_out){
					error_log("OK: checkoutAction checked out repository ".$repository_path." to ".$local_path_container);
				}else{
					error_log("ERROR: checkoutAction could not check out repository ".$repository_path." to ".$local_path_container);
				}

			}
		}else{
			//error: local_path not writable
			error_log("ERROR: checkoutAction local path ".$local_path." is not writable.");
		}
	}else{
		//error: local path does not exist
		error_log("ERROR: checkoutAction local path ".$local_path." does not exist.");
	}
	$this->view->jsonstring = json_encode($retarray);
    }
}
?>
