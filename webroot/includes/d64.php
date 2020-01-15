<?php

spl_autoload_register(function($className)
{
	if(!@include("$className.php"))
		exit('Sysop is ziek');
});

define('ROOTPATH',str_replace("/includes","",__DIR__));

class d64 {
  private array $pages;
  private array $virtual_paths;
  public array $path;
  private array $parsed_url = ['path'=>'','url'=>''];
  private string $title;
  private string $content;

  private array $module;

  public function __construct()
  {
    if(!$this->get_config())
      exit('Can\'t parse config');
    if(isset($_SERVER['REQUEST_URI']))
      $this->parsed_url = parse_url($_SERVER['REQUEST_URI']);
    $this->path = array_values(array_filter(explode("/",$this->parsed_url['path'])));
  }

  private function get_config() : bool
  {
    if($config = @file_get_contents(ROOTPATH."/includes/d64.conf.json")){
      // True will make it into an array..
      if($this->pages = json_decode($config,true))
        return true;
      else return false;
    }else return false;
  }

  public function module(string $mod)
  {
    if(isset($this->module[$mod]))
      return $this->module[$mod];
    else{
      exit($mod.' meditation');
    }
  }

  public function set_title(string $title) : void
  {
    $this->title = $title;
  }

  public function set_content(string $content) : void
  {
    $this->content = $content;
  }

  public function get_content() : string
  {
    if(empty($this->content))
      $this->content = "Nothing to display";
    return $this->content;
  }

  public function get_path() : array
  {
    return $this->path;
  }

  public function get_parsed_url() : array
  {
    return $this->parsed_url;
  }

  public function get_pages() : array
  {
    return $this->pages;
  }

  public function get_title(): string
  {
    if(isset($this->title))
      return $this->title;
    else return "";
  }

  public function init() : bool
  {
    /*
    * Trying to have as less foreaches possible so we may use this for navigation bar also..
    *
    * Search for modules who has to be started always
    */

    $this->virtual_paths = $this->parse_config($this->pages);

    /*
    * Catch the root page
    */

    if(empty($this->path[0])){
        if(isset($this->parsed_url['query']) &&
           !$this->query_string_check($this->parsed_url['query'],$this->pages['root'])){
          $this->not_found();
        }else{
         $this->title = $this->pages['root']['title'] ?? "Frontpage";
        }
    }

    /*
    * Catch other pages/maps
    */

    elseif(isset($this->pages[$this->path[0]])){
      switch ($this->pages[$this->path[0]]['data']){
        case 'virtual':
          $this->title = $this->pages[$this->path[0]]['title'] ?? "";
          $this->parse_virtual_dirs($this->virtual_paths);
          if(isset($this->pages[$this->path[0]]['class']) && empty($this->module[$this->path[0]])){
            $this->module[$this->path[0]] = new $this->pages[$this->path[0]]['class']($this);
          }
          break;
        case 'real':
          if(isset($_GET['404']) ||
             isset($this->parsed_url['query']) &&
             !$this->query_string_check($this->parsed_url['query'],$this->pages[$this->path[0]])){
            $this->not_found();
          }else{
            if(isset($this->pages[$this->path[0]]['class']) && empty($this->module[$this->path[0]])){
              $this->module[$this->path[0]] = new $this->pages[$this->path[0]]['class']($this);
            }
          }
          break;
        default:
          $this->not_found();
      }
    }else{
      $this->not_found();
    }
    return true;
  }

  private function parse_config(array $tree, string $parent = '') : array
  {
    $paths = [];

    if(!empty($parent)){
      $parent = $parent.'/';
    }

    foreach($tree as $key => $value){
      if(isset($this->header)){
        $this->header->navigation($key,$value,str_replace('/','',$parent));
      }
      if(isset($value['class']) && isset($value['always'])){
        $this->module[$key] = new $this->pages[$key]['class']($this);
      }
      if(is_array($value)){
        $currentPath = $parent.$key;
        $query_strings = $value['query_strings'] ?? "";
        $paths[] = ["path"=>$currentPath, "query_strings"=>$query_strings];
        if(isset($value["sub"])){
          $paths = [...$paths, ...$this->parse_config($value["sub"], $currentPath)];
        }
      }
    }

    return $paths;
  }

  /*
  * Return ??
  */

  private function parse_virtual_dirs(array $dirs) : bool
  {
    if($id = array_search(preg_replace(['/^\//','/\/$/'],'', $this->parsed_url['path']), array_column($dirs, 'path'), true)){
      $query = "";
      if(isset($this->parsed_url['query'])){
        if(!$this->query_string_check($this->parsed_url['query'], $dirs[$id])){
          $this->not_found();
          return false;
        }else{
          $query = '?'.$this->parsed_url['query'];
        }
      }
      if($this->parsed_url['path'][-1]!='/'){
        header("Location: ".URL.$this->parsed_url['path']."/".$query,true,301);
        return false;
      }else{
        http_response_code(200);
        return true;
      }
    }else{
        $this->not_found();
        return false;
    }
  }

  private function query_string_check(string $to_check, array $config) : bool
  {
    parse_str($to_check,$query_string_array);
    foreach($query_string_array AS $key => $value){
      if(!isset($config['query_strings'][$key])){
        return false;
      }
    }
    return true;
  }

  public function not_found()
  {
    $this->title = "Whoops";
    $this->content = "404 niet gevonden";
    http_response_code(404);
  }
}

?>
