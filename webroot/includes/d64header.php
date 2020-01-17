<?php

class d64Header{
  private object $d64;
  private string $description = "";
  private string $inline_css = "";
  private string $script = "";
  private string $nav = "";
  private string $parent;

  function __construct($d64)
  {
    $this->d64 = $d64;
  }

  public function set_inline_css(string $css)
  {
    $this->inline_css = '<style>'.$css.'</style>';
  }

  public function set_script(string $script)
  {
    $this->script = $script;
  }

  public function set_description(string $desc)
  {
    $this->description = $desc;
  }

  public function make_header() : string
  {
	return '<!DOCTYPE html>'.
		'<html lang="nl">'.
		'<head>'.
		'<meta name="viewport" content="width=device-width, initial-scale=1">'.
		'<meta name="theme-color" content="#616161">'.
		'<link rel="stylesheet" href="/css/w3.css">'.
		'<link rel="manifest" href="/manifest.json">'.
		'<title>'.$this->d64->get_title().'</title>'.
		'<meta name="description" content="'.$this->description.'">'.
		$this->inline_css.
		$this->script.
		'</head>'.
		'<body>'.
		'<div class="w3-bar w3-top w3-black" id="nav">'.
			$this->nav.
			'<span class="w3-bar-item" id="life">&#9679;</span>'.
		'</div>';
  }

  public function navigation(string $key, array $value, string $parent) : bool
  {
    if(isset($value['nav']) && $value['nav']['enable']==='yes'){
      if($key==='root'){
        $path = '/';
      }elseif(isset($value['path'])){
        $path = $value['path'];
      }
      if(isset($path)){
        $this->nav .= '<a href="'.$path.'" class="w3-bar-item w3-button">'.$value['nav']['name'].'</a>';
        return true;
      }else{
        return false;
      }
    }
    return false;
  }
}

?>
