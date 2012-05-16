<?php
class StringBuilder
{
    private $lines = array();
    private $separator;
    private $terminator;    

    public function __construct($separator = "\n", $terminator = "")
    {
        $this->separator = $separator;
        $this->terminator = $terminator;
    }

    public function getNumLines() { return count($this->lines); }

    public function addLine($indentLevel = 0, $line = '')
    {
        $tabs = "";
        for($i = 0; $i < $indentLevel; $i++) {
            $tabs .= "    ";
        }

        $this->lines[] = $tabs . $line;
    }

    public function add($stringBuilder)
    {     
        $this->lines[] = $stringBuilder->toString();
    }

    public function addBlankLine()
    {
        $this->lines[] = "";
    }

    public function addToLastLine($str)
    {
        $index = count($this->lines) - 1;
        $this->lines[$index] = $this->lines[$index] . $str;
    }
    
    public function removeTrailingCharFromLastLine()
    {
        $index = count($this->lines) - 1;
        $this->lines[$index] = substr($this->lines[$index], 0, strlen($this->lines[$index]) - 1);       
    }    

    public function toString()
    {
        return implode($this->separator, $this->lines) . $this->terminator;
    }

    public function save($filename, $overwriteExisting = true)
    {
        if ($overwriteExisting == false && file_exists($filename)) {
            return;
        }
        file_put_contents($filename, $this->toString());
    }
}
?>