<?php

class d64Blog{
  private object $d64;

  public function __construct($d64)
  {
    $this->d64 = $d64;

    if(isset($this->d64->get_path()[0]) && $this->d64->get_path()[0]==='blog'){
      if(isset($_GET['404'])){
        $this->d64->not_found();
      }
      /*
      * When a trailing slash is missing the the request does exists!
      * We only want to serve news articles
      */
      elseif($this->d64->get_parsed_url()['path'][-1]!='/'){
        if(empty($this->d64->get_path()[4])){
          $path = $this->d64->get_path();
          if(end($path)!='content.php'){
            $query_strings = (isset($this->d64->get_parsed_url()['query'])) ? '?'.$this->d64->get_parsed_url()['query'] : "";
            header("Location: ".URL.$this->d64->get_parsed_url()['path']."/".$query_strings,true,301);
          }else{
            $this->d64->not_found();
          }
        }elseif(preg_match('/^\/blog\/\d{4}\/\d{1,2}\/\d{1,2}\/.[^.]*$/',$this->d64->get_parsed_url()['path'])){
          $this->d64->setContent($this->nieuwsformatter($this->d64));
        }
      }else{
        if(is_file(ROOTPATH.$this->d64->get_parsed_url()['path'].'content.php')){
          $this->d64->set_title("Blog");
          ob_start();
          include(ROOTPATH.$this->d64->get_parsed_url()['path'].'content.php');
          $this->d64->setContent(str_replace("\n","",ob_get_contents()));
          ob_end_clean();
        }else{
          $this->d64->not_found();
        }
      }
    }
  }

  public function get_news(string $output = '') : string
  {
    $nieuws = explode("\n",file_get_contents(ROOTPATH.'/cache/last_10.txt'));
    array_pop($nieuws);

    foreach($nieuws AS $key => $value){
	$nieuws_item = explode("/",$value);
	if(end($nieuws_item))
		$output .= '<li id="nH-'.$key.'"><a href="'.$value.'">'.ucfirst(str_replace("_"," ",end($nieuws_item))).'</a></li>';
    }

	if($output!='')
		return '<ul class="nH">'.$output.'</ul>';
	else
		return 'Geen nieuws';
  }

	private function nieuwsformatter() : string
	{
		if(!empty($this->d64->get_path()[4])){
			$file = preg_split("/(?:\r\n?|\n){2,}/",file_get_contents(ROOTPATH.$this->d64->get_parsed_url()['path']));
			if(count($file)>=5){
				$artikel = [
					"date"  => new DateTime($this->d64->get_path()[3].'-'.$this->d64->get_path()[2].'-'.$this->d64->get_path()[1]),
					"auteur" => $file[0],
					"title"  => $file[1],
					"korte_inleiding" => $file[2],
					"inleiding" => str_replace("\n","<br/>",$file[3]),
					"artikel_formatted" => ""
				];
				$artikel["artikel"] = array_slice($file,4);
				$this->d64->set_title($artikel["title"]);
				foreach($artikel["artikel"] AS $key => $value){
					$artikel["artikel_formatted"] .= '<p>'.str_replace("\n","<br/>",$value).'</p>';
				}
				return '<p class="w3-small">'.
					$artikel["date"]->format("d-m-Y").', auteur '.$artikel["auteur"].
					'</p><p>'.
					$artikel["title"].
					'</p><p>'.
					$artikel["inleiding"].
					'</p>'.
					$artikel["artikel_formatted"];
			}else return '<p class="w3-small">Meditation needed</p>';
		}
	}
}

?>
