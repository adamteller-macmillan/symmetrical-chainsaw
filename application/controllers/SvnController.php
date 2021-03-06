<?php
date_default_timezone_set('America/New_York') ;

class SvnController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }
 
    function clearlocksAction(){
    	$_environment = $this->getRequest()->getParam('environment');
		$this->getHelper('layout')->setLayout('ajax');
		$_files  = $this->getListOfLockFiles($_environment);
		$_result = array();
		foreach($_files as $_file){
			$_subtype = $_file[0];
			$_result[$_subtype] = $this->deleteLockFile($_subtype,$_environment);
		}
		$this->view->result = $_result;
    }

   public function deleteAction(){
		$this->getHelper('layout')->setLayout('ajax');
	
		$retarray 				= array();
		$svn_committed   		= 0;
		$subtype		   	    = $this->getRequest()->getParam('subtype');
		$environment			= $this->getRequest()->getParam('environment');
		$repository			    = $this->getRequest()->getParam('repository');
		$this->view->repository = $repository;
		$svn_path_base_local   		= $this->getLocalBasePath($subtype,$environment);

		$retarray['svn_deleted'] 		    = "0";
		$retarray['message']			    = "FAILED";
		$retarray['subtype'] 		        = $subtype;
		$retarray['svn_path_base_local']	= $svn_path_base_local;

		$svn_delete	   		= svn_delete($svn_path_base_local,TRUE);
		if($svn_delete){
			$svn_committed        	= svn_commit("deleting subtype ".$subtype,array($svn_path_base_local));

			$svn_deleted				    = $svn_committed[0];
			$retarray['svn_deleted']		= $svn_deleted;
			if($svn_deleted){
				$this->deleteExistingDownloads($subtype,$environment); 
				$retarray 			= array_merge($retarray,$this->doRemoteReinit($subtype));
				$retarray['message'] 	 	= "OK";
			}
		}
		$this->view->msg = json_encode($retarray);
   }
   /** this is the 'important' action called by the Digfir app (see promptdownloadAction in Digfir SnvController.php **/

   public function downloadzipAction(){
	
		error_log("======");
		$subtype        = $this->getRequest()->getParam('subtype');
		$environment    = $this->getRequest()->getParam('environment');
		$staging        = $this->getRequest()->getParam('staging');
		$repository     = $this->getRequest()->getParam('repository');
		$digfir         = $this->getRequest()->getParam('digfir');
		$keysent        = $this->getRequest()->getParam('svnrelaykey');
		$_user          = $this->getRequest()->getParam('user');

		$this->view->repository = $repository;
		$this->deleteLockFile($subtype,$environment);

		$_key       = $this->getKey();
		$_do_download = false;
		if(!empty($_key)){
			error_log("local key is defined. validation from digfir required.");
			if($_key===$keysent){
				error_log("local key matches key passed from digfir.");
				$this->view->digfir 	= $digfir;
				if($digfir){
					error_log("processing download request from digfir...");
				}
				if($subtype){
					$_do_download = true;	
				}else{
					$this->view->message = "ERROR: The subtype must be specified.";	
				}
			}else{
				error_log("the svnrelaykey value sent from digfir (".$keysent.") was invalid.");
				$this->view->message = "ERROR: the svnrelay key sent from digfir was invalid.";
			}
		}else{
			error_log("local key is not defined. validation of digfir app not performed.");
			$_do_download = true;
		}
		if($_do_download){
			$_is_locked  = TRUE;
			$_lock_level = $this->getLockLevel();
			error_log("found lock level to be ".$_lock_level);
			if($_lock_level=="all"){
				//keep locked if any lock exists for any subtype
				$_locks = $this->getListOfLockFiles($environment);
				if($_locks && count($_locks)>0){
					$_lock = $_locks[0];
					if($_lock){
						$this->view->message = "WARNING: The subtype ".$subtype." is currently locked due to a commit of subtype '".$_lock[0]."' by user '".$_lock[3]."' initiated on ".date("D M j G:i:s T Y",$_lock[2]).". Please wait and try again in a few minutes. If this persists please contact an administrator. (lock level=all)";
					}else{
						$_is_locked = FALSE;
					}
				}else{
					$_is_locked = TRUE;
				}
			}else if($_lock_level=="subtype"){
				$_lock	= $this->getLockFileContents($subtype,$environment);
				if($_lock==NULL){
					$_is_locked = FALSE;
				}else{
					$this->view->message = "WARNING: The subtype ".$subtype." is currently locked due to a commit by user '".$_lock[3]."' initiated on ".date("D M j G:i:s T Y",$_lock[2]).".  Please wait and try again in a few minutes. If this persists please contact an administrator. (lock level=subtype)";
				}
			}else{
				$_is_locked = FALSE;
			}
			if(!$_is_locked){

				if($_lock_level=="subtype" || $_lock_level=="all"){
					$user = $_user;
					$this->writeLockFile($subtype,$user,$environment);
				}
				$this->view->success = $this->doDownloadZip($subtype,$environment,$staging);
				if($subtype!=='beta'){
					$this->deleteLockFile($subtype,$environment);
				}
			}else{
				$retarray	     = array();
				$retarray['message'] = $this->view->message;
				$this->view->msg     = json_encode($retarray);
				$this->getHelper('layout')->setLayout('ajax');
			}
		}else{
			$retarray	     = array();
			$retarray['message'] = $this->view->message;
			$this->view->msg     = json_encode($retarray);
			$this->getHelper('layout')->setLayout('ajax');
			//error_log($this->view->msg);
		}
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
   		//fix this
   		$environment = 'platinum';
		//$this->do_repository = 1;//$this->getRequest()->getParam('dorepository');
	
		$this->do_repository = 1;
		$this->getHelper('layout')->setLayout('ajax');

		$this->view->svn_path_base_remote  = $this->getRemoteBasePath();//$svn_path.$svn_repository;
		$this->view->svn_path_base_local   = $this->getLocalBasePath();//$svn_local.$svn_repository;

		$this->view->digfirfiles_url	   = $this->getDigfirfilesUrl();

		$this->view->lock_files 	   = $this->getListOfLockFiles($environment);
	
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

   public function listAction(){
    	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		$this->subtype 			= $this->getRequest()->getParam('subtype');
 		$this->getHelper('layout')->setLayout('ajax');
		$this->view->svn_list 	= $this->listRemote($this->getRemoteBasePath()."/".$this->subtype);
   }
   public function listlocalAction(){
   	    $_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		$this->subtype = $this->getRequest()->getParam('subtype');
 		$this->getHelper('layout')->setLayout('ajax');
		$this->view->svn_list = $this->get_subdir_files($this->getLocalBasePath()."/".$this->subtype);
   }

   function listlocksAction(){
   	   	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		$this->getHelper('layout')->setLayout('ajax');
		$this->view->lock_files = $this->getListOfLockFiles($_environment);
    }
   public function localworkingcopyexistsAction(){
   	   	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		$this->subtype = $this->getRequest()->getParam('subtype');
		$this->getHelper('layout')->setLayout('ajax');
		$svn_path_base_local      = $this->getLocalBasePath();//$svn_local.$svn_repository;
		$svn_path_subtype_local   = $svn_path_base_local."/".$this->subtype;
		$this->view->localworkingcopyexists = file_exists($svn_path_subtype_local."/.svn")? "1" : "0";
		$this->view->message = $this->view->localworkingcopyexists? "local working copy exists" : "local working copy does not exist";
		$this->view->svn_path_subtype_local = $svn_path_subtype_local;
   }
    function lockAction(){
   		$_environment = $this->getRequest()->getParam('environment');
		$this->view->lock_dir          = $this->getLockDir($_environment);
		$this->view->lock_dir_exists   = file_exists($this->view->lock_dir);
		$this->view->lock_dir_writable = is_writable($this->view->lock_dir);
		$this->view->lock_files	       = $this->getListOfLockFiles($_environment);
    }
    public function lsAction(){
    	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
        $this->getHelper('layout')->setLayout('ajax');
		$this->view->svn_ls     = $this->lsRemote($_repository);
		$this->view->message    = "svn list successful";
    }
    public function reinitAction(){
       	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		$this->getHelper('layout')->setLayout('ajax');
		$subtype = $this->getRequest()->getParam('subtype');
	
		error_log("reinit action called with subtype ".$subtype);
		$retarray = array();
		$retarray['message'] = "OK";
		$retarray['subtype']    = $subtype;
	
		if($subtype){
			$svn_path_subtype_local 	= $this->getLocalBasePath($subtype);

		
			if(file_exists($svn_path_subtype_local)){
				$retarray['local_path_exists']  = TRUE;
				$svn_path_deleted		= $this->delete_directory($svn_path_subtype_local);	
			}else{
				$retarray['local_path_exists']  = FALSE;
				$svn_path_deleted = FALSE;
			}
			if($svn_path_deleted==NULL){
				$svn_path_deleted = FALSE;
			}
			$retarray['local_path']     	= $svn_path_subtype_local; 
			$retarray['local_path_deleted'] = $svn_path_deleted;

			$retarray = array_merge($retarray,$this->doRemoteReinit($subtype));	
		
		}
		$this->view->msg = json_encode($retarray);
    }
    function removelockAction(){
		$this->getHelper('layout')->setLayout('ajax');
		$retarray = array();
		$retarray['result'] = '0';
		$_subtype     = $this->getRequest()->getParam('subtype');
		$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
		if($_subtype){
			$this->view->result = $this->deleteLockFile($_subtype,$_environment);
			if($this->view->result){
				$retarray['result'] = '1';
			}
		}
		$this->view->retarray = $retarray;
    }
   public function statusAction(){
      	$_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
        $this->subtype = $this->getRequest()->getParam('subtype');
		$this->getHelper('layout')->setLayout('ajax');
		$this->view->svn_status = svn_status($this->getLocalBasePath()."/".$this->subtype);	  
   } 
   public function updateAction(){
   	    $_environment = $this->getRequest()->getParam('environment');
		$_repository  = $this->getRequest()->getParam('repository');
		$this->view->repository = $_repository;
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
   public function uploadfileAction(){
    	error_log("====== uploadfileAction");

    	$this->getHelper('layout')->setLayout('ajax');

		$_subtype       = $this->getRequest()->getParam('subtype');
		$_environment   = $this->getRequest()->getParam('environment');
		$_directory     = $this->getRequest()->getParam('directory');
		$_filename      = $this->getRequest()->getParam('filename');
		$_user          = $this->getRequest()->getParam('user');
		$_content		= $this->getRequest()->getParam('content');
		$_repository  	= $this->getRequest()->getParam('repository');
		$_staging       = $this->getRequest()->getParam('staging');

		$this->view->repository = $_repository;

		$retarray	     		   = array();
		$retarray['status']        = 1;
		$retarray['message'] 	   = "OK";
		$retarray['subtype']       = $_subtype;
		$retarray['directory']     = $_directory;
		$retarray['filename']      = $_filename;
		$retarray['user']          = $_user;

		$retarray['contentlength'] = isset($_content)? strlen($_content) : '-1';

		$_remoteexists = $this->repositoryExists($_subtype);
		if(!$_remoteexists){
			$retarray['status']        = 0;
			$retarray['message'] 	   = "The subtype ".$_subtype." has not yet been committed to the repository.";
			//$this->view->remotecreated = $this->createRemoteRepository($subtype);	
		}else{
			 
			$_update_result			   = $this->updateLocalWorkingCopyWithFile($_subtype,$_directory,$_filename,$_content);
			$retarray['result']        = array();
			$retarray['status']        = 0;

			$retarray['result']['localupdated']  = $_update_result[0];
			$retarray['result']['oldversion']    = $_update_result[1];
			$retarray['result']['newversion']    = $_update_result[2];
			$retarray['result']['updateerror']   = $_update_result[3];

			$_local_updated = $retarray['result']['localupdated'];
			if($this->updateRemoteFiles()){
				$retarray['digfirfiles_url'] = $this->getDigfirfilesPublicUrl($_subtype,$_environment,$_staging);
				if($_local_updated){
					$remoteupdated = $this->promptRemoteUpdate($_subtype,$_environment,$_staging);	
					if($remoteupdated){
						$retarray['remoteupdated']   = json_decode($remoteupdated);
						$retarray['message'] 	     = "The file was commited to the repository and checked out to the staging server at <a href=\"".$retarray['digfirfiles_url']."\">".$retarray['digfirfiles_url']."</a>";
					}else{
						$retarray['message'] 	     = "The file was commited to the repository but was not checked out to the staging server.";
					}
				}	
			}else{
				$retarray['message'] 	     = "The file was commited to the repository but was not checked out to the staging server.";
			}
			
		}
		$this->view->msg           = json_encode($retarray);
		//error_log("return message: ".$this->view->msg);
    }


   public function getRemoteBasePath($subtype=null){
		$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		$svn_path       = $options['svnrelay']['svn_path'];
		//$svn_repository = $options['svnrelay']['svn_repository'];
		//this uses repository variable passed from Digfir now
		$svn_repository = $this->view->repository;
		$_path = $svn_path.$svn_repository;
		if($subtype){
			$_path = $_path .= "/".$subtype;
		}
		return $_path;
   }
   public function getLocalBasePath($subtype=null,$environment=null){
		$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		$svn_local      = $options['svnrelay']['svn_local'];
		//$svn_repository = $options['svnrelay']['svn_repository'];
		//this uses repository variable passed from Digfir now
		$svn_repository = $this->view->repository;
		$_path = $svn_local.$svn_repository;
		if($subtype){
			$_path .= "/".$subtype;
		}
		return $_path;
   }

   //MT: fix here allows the dev, staging, and production versions of digfir to work simultaneously with the same svnrelay app
   public function getDigfirUrl(){
	//$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	//$options        = $bootstrap->getOptions();
	//return $options['svnrelay']['digfir_url'];
		$this->view->digfirhost     = $this->getRequest()->getParam('digfirhost');
		$this->view->digfirusehttps = $this->getRequest()->getParam('digfirusehttps');

		$digfir_protocol = $this->view->digfirusehttps==="1" ? "https" : "http"; 
		$digfir_url      = $digfir_protocol."://".$this->view->digfirhost."/";
		error_log("using digfir url ".$digfir_url);
		return $digfir_url;
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
   public function getDigfirfilesPublicUrl($subtype,$environment,$staging){
		//$_base_url = $this->getDigfirfilesUrl();
		$bootstrap 		= Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		$_public_url    = $options['svnrelay']['digfirfiles_public_url'];
		//$_public_url = $_base_url."files/";
		if($staging){
			$_public_url = $_public_url . $staging."/";
		}
		if($subtype){
			$_public_url = $_public_url . $subtype."/";
		}
		return $_public_url;
   }
   public function getDigfirfilesUpdateUrl($subtype=null,$environment=null,$staging=null){
		$digfirfiles_url = $this->getDigfirfilesUrl();
		if($digfirfiles_url ==null) return null;
		$digfirfiles_update_url     = $digfirfiles_url."svn/update/repository/".$this->view->repository.'/environment/'.$environment.'/staging/'.$staging;

		if($subtype){
			$digfirfiles_update_url    = $digfirfiles_update_url     . "/subtype/".$subtype;
		}
		return $digfirfiles_update_url;
   }

    /******* lock functions ****/
    public function getLockLevel(){
		$bootstrap 	= Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		$_level         = $options['svnrelay']['lock_level'];
		return $_level;	
    }
    public function getLockDir($environment=null){
		$bootstrap 	= Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		$_dir           = $options['svnrelay']['lock_dir'];
		if($environment){
			$_dir .= '/'.$environment;
		}
		if(!file_exists($_dir)){
			error_log("creating lock directory: ".$_dir);
			mkdir($_dir);
		}else{
			error_log("using lock directory: ".$_dir);
		}
		
		return $_dir;
    }
    function getLockFilePath($subtype,$environment){
		$_dir  = $this->getLockDir($environment);
		$_path = $_dir."/".$subtype.".lock";

		return $_path;
    }
    function writeLockFile($subtype,$user,$environment){
    	$_lock_file_path = $this->getLockFilePath($subtype,$environment);
    	error_log("attempting to write lock file to ".$_lock_file_path);
		return file_put_contents($_lock_file_path,$user);
    }
    function deleteLockFile($_subtype,$_environment){
		$_path = $this->getLockFilePath($_subtype,$_environment);
		if(file_exists($_path)){
			return unlink($_path);
		}
		return FALSE;
    }
    function getLockFileContents($_subtype,$_environment){
		$_path = $this->getLockFilePath($_subtype,$_environment);
		if(file_exists($_path)){
			return array($_subtype, $_path, filemtime($_path), file_get_contents($_path),date("D M j G:i:s T Y",filemtime($_path)));
		}
		return null;
    }
    function getListOfLockFiles($environment){
		$_dir   = $this->getLockDir($environment);
		$_files = scandir($_dir);
		$_lock_files = array();
		foreach($_files as $_file){
			$_path_info = pathinfo($_file);
			if($_path_info['extension']=="lock"){
				$_subtype      = $_path_info['filename'];
				$_lock_files[] = $this->getLockFileContents($_subtype,$environment);
			}
		}	
		return $_lock_files;	
    }

  




	public function updateRemoteFiles(){
		$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$options        = $bootstrap->getOptions();
		return $options['svnrelay']['update_remote_files'];
   }


   public function getKey(){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$_key      = $options['svnrelay']['key'];
	return $_key;
   }
   public function getDownloadDir($environment=null){
	$bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	$options        = $bootstrap->getOptions();
	$_dir           = $options['svnrelay']['download_dir'];
	error_log("found base download directory as ".$_dir);
	if($environment){
			$_dir .= "/".$environment;
			
			if(!file_exists($_dir)){
				error_log("making environment download directory ".$_dir);
				mkdir($_dir);
			}else{
				error_log("using environment download directory ".$_dir);
			}
	}
	/**
	//removed
	if($subtype){
		
		
		$_dir .= "/".$subtype;	
		if(!file_exists($_dir)){
			error_log("making subtype download directory ".$_dir);
			mkdir($_dir);
		}else{
			error_log("using subtype download directory ".$_dir);
		}		
		
	}
	**/
	return $_dir;
   }


   public function updateLocalWorkingCopyWithFile($subtype,$directory,$filename,$content){
   	$svn_path_subtype_local 	    = $this->getLocalBasePath($subtype);
   	$svn_subtype_current_version    = svn_update($svn_path_subtype_local);
   	$svn_subtype_new_version        = $svn_subtype_current_version;
	$svn_subtype_updated            = 0;
	$svn_update_error               = 0;
	$svn_subtype_committed          = null;
	$_commited						= false;
	error_log("svn update found current version of ".$subtype." to be ".$svn_subtype_current_version);


	$current_working_copy_file_path   = $svn_path_subtype_local."/".$directory."/".$filename;

	$current_working_copy_file_exists = file_exists($current_working_copy_file_path);
	error_log("svn looking for current file at ".$current_working_copy_file_path.", exists=".$current_working_copy_file_exists);

	$_do_commit = false;
	$_commited  = false;
	if($current_working_copy_file_exists){
		error_log("current working copy of file does not exists.");
		if(strlen($content)==0){
			error_log("new file has zero length.");
			$_delete_path = $current_working_copy_file_path;
			error_log("found zero content length for new file-->file will be deleted.");
			$_deleted = true;
			//$_deleted = @svn_delete($_delete_path,TRUE);
			error_log("svn delete ".$_delete_path." ".$_deleted);
			$_do_commit = true;
		}else{
			error_log("saving content to ".$current_working_copy_file_path);
			error_log($content);
			if(file_put_contents($current_working_copy_file_path,$content)){
				error_log("saved content to ".$current_working_copy_file_path);
				$_do_commit = true;
			}else{
				error_log("did not save content to ".$current_working_copy_file_path);
			}
		}
	}else{
		error_log("current working copy of file does not exist.");
		//add file
		if(file_put_contents($current_working_copy_file_path,$content)){
			error_log("wrote content to current working copy and adding.");
			$_add_path = $current_working_copy_file_path;
			@svn_add($_add_path);	
			$_do_commit = true;
		}
	}
	error_log("svn status ".$svn_path_subtype_local);
	$this->view->status = @svn_status($svn_path_subtype_local);
	if($_do_commit && is_array($this->view->status)){
		$_status_size       = count($this->view->status);
		error_log("svn status found ".$_status_size." items.");
		
		if($_status_size>0){

			error_log("svn commiting added or modified file for ".$svn_path_subtype_local);

					//MT: error suppression from this error which may be due to a bug in the API
					//https://bugs.php.net/bug.php?id=60583
					/**
			[Thu May 24 17:06:20 2012] [error] [client 127.0.0.1] PHP Warning:  svn_commit(): svn error(s) occured\n200031 (Attempted to write to readonly SQLite db) Commit failed (details follow):\n200031 (Attempted to write to readonly SQLite db) attempt to write a readonly database\n200031 (Attempted to write to readonly SQLite db) attempt to write a readonly database\n in /var/www/bfw-svnrelay/application/controllers/SvnController.php on line 89
			NOTE file is added correctly but generates error on commit, but then shows up in repository
					**/
			//$svn_subtype_committed = true;
			$svn_subtype_committed  = @svn_commit("commiting updated subtype ".$subtype,array($svn_path_subtype_local));
			$_commited		= true;
					//$this->view->committed = $svn_subtype_committed;

					


		}else{
			error_log("svn not commiting ".$subtype." because no changes to file were found.");
		}
	}else{
		error_log("svn found null status for ".$subtype);
	}
	if($_commited){
			//This generates an 'E' status on the command line, but seems to make things work
			$svn_subtype_new_version    = @svn_update($svn_path_subtype_local);
			$svn_subtype_updated        = $svn_subtype_new_version > $svn_subtype_current_version ? "1" : "0";
			error_log("svn repository for ".$subtype." after commit of file now at version ".$svn_subtype_new_version);
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
	return array($svn_subtype_updated, $svn_subtype_current_version, $svn_subtype_new_version,$svn_update_error);
   	//return(true,1,2,null);
   }
 
   public function updateLocalWorkingCopy($subtype,$environment=null){
   	error_log("updateLocalWorkingCopy called for subtype ".$subtype." and environment ".$environment);
	$svn_path_subtype_local 	    = $this->getLocalBasePath($subtype);
	error_log("updating working copy at ".$svn_path_subtype_local);
	$svn_subtype_current_version    = svn_update($svn_path_subtype_local);
	error_log("svn update found current version of ".$subtype." to be ".$svn_subtype_current_version);
    $svn_subtype_new_version        = $svn_subtype_current_version;
	$svn_subtype_updated            = 0;
	$svn_update_error               = 0;
	$svn_subtype_committed          = null;
	$_commited			= false;
	$files_deleted			= array();
	$files_added			= array();

	//MT: this must be set to TRUE in order to avoid conflict errors for commits involving both deletions and additions/modifications
	$_do_separate_commit_for_deletions = TRUE;

	

	$_extracted 		 = $this->getDownloadDir($environment);
	$_extracted         .= "/".$subtype;

	error_log("found extracted directory from getDownloadDir to be ".$_extracted);

	if(file_exists($_extracted)){
		error_log("extracted file exists at download directory ".$_extracted);
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
   public function createRemoteRepository($subtype,$environment=null){
   	$fromextracted = true;
   	error_log("creating remote repository");
	$svn_subtype_committed  = null;
	$svn_path_subtype_local = $this->getLocalBasePath($subtype);
	//MT: subtype does not exist in remote repository
	//MT: first check to see if local working copy dir exists
	if(file_exists($svn_path_subtype_local)){
		//delete working copy dir---for error failover in case dir has been created by accident without checkin
		error_log("deleting unchecked in local copy at ".$svn_path_subtype_local);
		$this->delete_directory($svn_path_subtype_local);
	}
	//MT: this condition should always be true if dir was deleted as per prev step---leave in for now
	if(!file_exists($svn_path_subtype_local)){
		//create local working copy directory
		//check in to remote repository
		error_log("creating local working copy of subtype ".$svn_path_subtype_local);
		if($fromextracted){
			$_extracted = $this->getDownloadDir($environment);
			$_extracted .= "/".$subtype;

			if(file_exists($_extracted)){
				//$svn_subtype_mkdir     = svn_mkdir($svn_path_subtype_local);
				$this->view->copied    = $this->copy_directory($_extracted,$svn_path_subtype_local);
				error_log("adding local working copy at ".$svn_path_subtype_local);
				$_added                = svn_add($svn_path_subtype_local);
			}
		}else if(!file_exists($svn_path_subtype_local)){
			$svn_subtype_mkdir     = svn_mkdir($svn_path_subtype_local);
		}
		error_log("commiting local subtype in ".$svn_path_subtype_local);
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
	error_log("compare_directory ".$path1." <---> ".$path2);

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





 

   public function testAction()
   {
	error_log("testAction called");
	$this->getHelper('layout')->setLayout('ajax');
	$this->view->subtype = $this->getRequest()->getParam('subtype');
        // action body
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



    public function doRemoteReinit($subtype){
		error_log("performing remote reinit for subtype ".$subtype);
		$retarray = array();
		$_url    =  $this->getDigfirfilesUrl();
		$_url    =  $_url.'svn/delete/subtype/'.$subtype;
	
		

		$client   = new Zend_Http_Client($_url, array(
    			'maxredirects' => 5,
    			'timeout'      => 30));
		$response = $client->request('POST');	
		try{
			$body 	  = $response->getBody();
			$msg      = json_decode($body,true);
			$retarray['remote_path_deleted'] = $msg['message']==="1";
			$retarray['remote_path']         = $msg['subtype_dir'];
			$retarray['remote_path_exists']  = $msg['subtype_dir_exists'];
			$retarray['remote_message']      = $msg['message'];
		}catch(Exception $e){
			$retarray['remote_path_deleted'] = FALSE;
			$retarray['remote_path']         = NULL;
			$retarray['remote_path_exists']  = NULL;
			$retarray['remote_message']      = $e->getMessage();
		}
		return $retarray();

    }


    //MT: performs login to DIGFIR application
    public function doRemoteLogin(){
    
	$digfir_url	= $this->getDigfirUrl();
	$url 		= $digfir_url.'user/login';
	error_log("doRemoteLogin to url ".$url);
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


    public function deleteExistingDownloads($subtype,$environment){

		$download_dir = $this->getDownloadDir($environment);

		$download_file = $download_dir."/".$subtype.".zip";

		if(file_exists($download_file)){
			unlink($download_file);
			error_log("deleting existing zip file ".$download_file);
		}else{
			error_log("not deleting existing zip file ".$download_file." because it does not yet exist.");
		}

		$extract_dir = $download_dir."/".$subtype;

		if(file_exists($extract_dir)){
			error_log("deleting extract directory ".$extract_dir);
			$this->delete_directory($extract_dir);		
		}else{
			error_log("not deleting extract directory ".$extract_dir. "because it does not yet exist.");
		}
		return array($download_dir,$download_file,$extract_dir);
    }

    public function doDownloadZip($subtype,$environment,$staging){
		error_log(">>>> doDownloadZip subtype=".$subtype.", environment=".$environment.", staging=".$staging);
		$zip_url	    = $this->getRequest()->getParam('zipurl');
		$bypass_login	= $this->getRequest()->getParam('bypasslogin');

		$client		= null;	
	
		if($bypass_login){
			error_log("bypassing login to digfir app");
			$url = $zip_url;
			$client   = new Zend_Http_Client($url,array(
    			'maxredirects' => 5,
			    'keepalive' => true,
    			'timeout'      => 30));
		}else{
			error_log("logging into digfir app");
			$client     = $this->doRemoteLogin();
			$digfir_url	= $this->getDigfirUrl();
			$url 		= $digfir_url.'subtype/downloadzip/id/'.$subtype.".zip";
			$client->setUri($url);	
		}
		$retarray = array();

		$downloads = $this->deleteExistingDownloads($subtype,$environment);
		$download_dir 	= $downloads[0];
		$download_file  = $downloads[1];
		$extract_dir    = $downloads[2];
 	

		error_log("commencing download of zip from ".$url);

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
		error_log("download message: ".$response->getStatus()." ".$response->getMessage()." content-type:".$ctype);
		error_log("download stream name: ".$response->getStreamName());

	
		error_log("download file name: ".$download_file);
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
				$this->view->remotecreated = $this->createRemoteRepository($subtype,$environment);	
			}else{
			 
			$_update_result= $this->updateLocalWorkingCopy($subtype,$environment);
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
	$retarray['diff']	          = $this->view->diff;
	$retarray['committed']	      = $this->view->committed;
	$retarray['filesadded']       = $this->view->filesadded;
	$retarray['filesdeleted']     = $this->view->filesdeleted;
	$retarray['statusafter']      = $this->view->statusafter;

	
	
	if($this->updateRemoteFiles()){
		error_log("updating remote files >>>>>");
		if($this->view->remotecreated || $this->view->localupdated){
			$remoteupdated = $this->promptRemoteUpdate($subtype,$environment,$staging);	
			if($remoteupdated){
				$retarray['remoteupdated']   = json_decode($remoteupdated);
			
			}
		}
		$retarray['digfirfiles_url'] = $this->getDigfirfilesPublicUrl($subtype,$environment,$staging);
	}
	
	$this->view->msg =	json_encode($retarray);
	
	return 1;


    }
    function promptRemoteUpdate($subtype,$environment,$staging){
		$digfirfiles_update_url = $this->getDigfirfilesUpdateUrl($subtype,$environment,$staging);

		if($digfirfiles_update_url){
			error_log("promptRemoteUpdate calling digfirfiles update with url ".$digfirfiles_update_url);
			$client   = new Zend_Http_Client($digfirfiles_update_url, array(
    			'maxredirects' => 5,
				'keepalive' => true,
    			'timeout'      => 30));
			//error_log("getting key");
			$_key = $this->getKey();
			if(!empty($_key)){
				//error_log("setting key as ".$_key);
				$client->setParameterPost('svnrelaykey',$_key);
			}
			//error_log("sending post request.");
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





    

    /**
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

   **/

}
?>
