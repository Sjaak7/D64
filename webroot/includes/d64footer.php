<?php

class d64Footer{
  private object $d64;

  public function __construct($d64)
  {
    $this->d64 = $d64;

  }

  public function make_footer() : string
  {
    return '<div class="w3-bar w3-bottom w3-black w3-padding" id="footer">'.
           '</div>'.
           '</body>'.
           '</html>';
  }
}

?>
