<?php

if ( isset($_POST['text']) )
{
	$text = explode("\n", str_replace("\r\n", "\n", trim($_POST['text'])));
	$chordline = false;
	echo "<pre>";
	foreach ( $text as $i => $line )
	{
		if ( $i == 0 )
		{
			echo "&lt;halftone title=\"" . htmlspecialchars(htmlspecialchars($line)) . "\"&gt;\n";
		}
		else if ( trim($line) == "" )
		{
			// do nothing
		}
		else if ( preg_match('/^(.*?(?:Verse|Chorus|Bridge).*?):\s*$/i', $line, $match) )
		{
			if ( $chordline )
			{
				$chordstack = preg_split("/([ \t]+)/", $chordline);
				echo '(' . implode(") (", $chordstack) . ")\n";
				$chordline = false;
			}
			
			if ( $i > 3 )
				echo "\n";
			echo "= {$match[1]} =\n";
		}
		else if ( preg_match('/^\((.*?(?:Verse|Chorus|Bridge).*?)\)$/i', $line, $match) )
		{
			if ( $chordline )
			{
				$chordstack = preg_split("/([ \t]+)/", $chordline);
				echo '(' . implode(") (", $chordstack) . ")\n";
				$chordline = false;
			}
			echo "\n[{$match[1]}]\n";
		}
		else if ( preg_match('/^(\s*([A-G][#b]?(?:m?7?|2|add9|sus4|[Mm]aj[79])?)(\/[A-G][#b]?)?\s*)*$/', $line) )
		{
			if ( $chordline )
			{
				// we have two chord lines in a row... treat the last one as a transition
				$chordstack = preg_split("/([ \t]+)/", $chordline);
				echo '(' . implode(") (", $chordstack) . ")\n";
			}
			
			// chord line
			$chordline = $line;
		}
		else if ( $chordline && trim($line) )
		{
			// combine chord line with text line
			$chordline = preg_split('/([ \t]+)/', $chordline, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if ( count($chordline) >= 2 && preg_match('/^\s*$/', $chordline[0]) && preg_match('/^\s*$/', $chordline[1]) )
			{
				$merger = array_shift($chordline);
				$chordline[0] .= $merger;
			}
			
			$chordstack = array();
			for ( $j = 0; $j < count($chordline); $j++ )
			{
				if ( $j == 0 && !preg_match('/^\s*$/', $chordline[$j]) )
				{
					$chordstack[] = "({$chordline[$j]})";
					if ( isset($chordline[$j+1]) )
					{
						$chordline[$j+1] .= str_repeat(' ', strlen($chordline[$j]));
					}
					continue;
				}
				// insert line up until this chord
				$chordstack[] = substr($line, 0, strlen($chordline[$j]));
				// chomp off the front of the line
				$line = substr($line, strlen($chordline[$j]));
				// insert this chord
				if ( isset($chordline[++$j]) )
				{
					if ( !empty($chordline[$j]) )
					{
						$chordstack[] = "({$chordline[$j]})";
						if ( isset($chordline[$j+1]) )
						{
							$chordline[$j+1] .= str_repeat(' ', strlen($chordline[$j]));
						}
					}
				}
			}
			$chordstack[] = $line;
			echo implode("", $chordstack) . "\n";
			$chordline = false;
		}
		else
		{
			// assume it's a lyric line without chords...?
			echo "$line\n";
		}
	}
	
	echo "&lt;/halftone&gt;";
	echo "</pre>";
	exit;
}

?>
<form method="post">
<p><textarea name="text" rows="20" cols="100"></textarea></p>
<p><input type="submit" value="Make Halftone" /></p>
</form>
