<?php

class d64Footer{
  private object $d64;

  public function __construct($d64)
  {
    $this->d64 = $d64;

  }

  public function make_footer() : string
  {
    return '<div class="w3-container w3-teal">'.
           '<p class="w3-center w3-small">'.
             '(c) 2020, source: <a href="https://github.com/Sjaak7/d64">Github.com/Sjaak7/d64</a>'.
           '</p>'.
           '</div>'.
           '</body>'.
           '</html>';
  }
}

?>
