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
             '<a href="/blog/">Blog</a> - <a href="https://github.com/Sjaak7/D64">Code</a> - &copy; 2020 - <a href="https://d64.nl">D64.nl</a>'.
           '</p>'.
           '</div>'.
           '</body>'.
           '</html>';
  }
}

?>
