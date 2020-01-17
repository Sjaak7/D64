<?php

class d64Footer{
	private object $d64;

	private string $footer = '';

	public function __construct($d64)
	{
		$this->d64 = $d64;
	}

	public function setFooter(string $footer) : void
	{
		$this->footer = $footer;
	}

	public function makeFooter() : string
	{
		return
			'<div class="w3-bar w3-bottom w3-black" id="footer">'.
				$this->footer.
			'</div>'.
			'</body>'.
			'</html>';
	}
}

?>
