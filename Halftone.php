<?php

/**!info**
{
  "Plugin Name"  : "Halftone",
  "Plugin URI"   : "http://enanocms.org/plugin/halftone",
  "Description"  : "Allows semantic input and transposition of chord sheets.",
  "Author"       : "Dan Fuhry",
  "Version"      : "0.1",
  "Author URI"   : "http://enanocms.org/",
  "Version list" : ['0.1']
}
**!*/

$plugins->attachHook('render_wikiformat_posttemplates', 'halftone_process_tags($text);');
$plugins->attachHook('html_attribute_whitelist', '$whitelist["halftone"] = array("title", "key");');
$plugins->attachHook('session_started', 'register_special_page(\'HalftoneRender\', \'Halftone AJAX render handler\', false);');

define('KEY_C', 0);
define('KEY_D', 2);
define('KEY_E', 4);
define('KEY_F', 5);
define('KEY_G', 7);
define('KEY_A', 9);
define('KEY_B', 11);
define('KEY_C_SHARP', 1);
define('KEY_E_FLAT', 3);
define('KEY_F_SHARP', 6);
define('KEY_G_SHARP', 8);
define('KEY_B_FLAT', 10);

define('ACC_FLAT', -1);
define('ACC_SHARP', 1);

$circle_of_fifths = array(KEY_C, KEY_G, KEY_D, KEY_A, KEY_E, KEY_B, KEY_F_SHARP, KEY_C_SHARP, KEY_G_SHARP, KEY_E_FLAT, KEY_B_FLAT, KEY_F);
$accidentals = array(
	KEY_C => ACC_FLAT,
	KEY_G => ACC_SHARP,
	KEY_D => ACC_SHARP,
	KEY_A => ACC_SHARP,
	KEY_E => ACC_SHARP,
	KEY_B => ACC_SHARP,
	KEY_F_SHARP => ACC_SHARP,
	KEY_C_SHARP => ACC_SHARP,
	KEY_G_SHARP => ACC_FLAT,
	KEY_E_FLAT => ACC_FLAT,
	KEY_B_FLAT => ACC_FLAT,
	KEY_F => ACC_FLAT
);

function get_consonants($root_key)
{
	global $circle_of_fifths;
	$first = $root_key;
	$key = array_search($root_key, $circle_of_fifths);
	$fourth = $circle_of_fifths[(($key - 1) + count($circle_of_fifths)) % count($circle_of_fifths)];
	$fifth = $circle_of_fifths[($key + 1) % count($circle_of_fifths)];
	
	$minor1 = $circle_of_fifths[($key + 2) % count($circle_of_fifths)];
	$minor2 = $circle_of_fifths[($key + 3) % count($circle_of_fifths)];
	$minor3 = $circle_of_fifths[($key + 4) % count($circle_of_fifths)];
	
	$result = array(
			'first' => $first,
			'fourth' => $fourth,
			'fifth' => $fifth,
			'minors' => array($minor1, $minor2, $minor3)
		);
	return $result;
}

function get_sharp($chord)
{
	return key_to_name(name_to_key($chord), ACC_SHARP);
}

function detect_key($chord_list)
{
	$majors = array();
	$minors = array();
	$sharp_or_flat = ACC_SHARP;
	// index which chords are used in the song
	foreach ( $chord_list as $chord )
	{
		// discard bass note
		list($chord) = explode('/', $chord);
		$match = array();
		preg_match('/((?:m?7?|2|add9|sus4|[Mm]aj[79])?)$/', $chord, $match);
		if ( !empty($match[1]) )
			$chord = str_replace_once($match[1], '', $chord);
		$sharp_or_flat = get_sharp($chord) == $chord ? ACC_SHARP : ACC_FLAT;
		$chord = get_sharp($chord);
		if ( $match[1] == 'm' || $match[1] == 'm7' )
		{
			// minor chord
			if ( !isset($minors[$chord]) )
				$minors[$chord] = 0;
			$minors[$chord]++;
		}
		else
		{
			// major chord
			if ( !isset($majors[$chord]) )
				$majors[$chord] = 0;
			$majors[$chord]++;
		}
	}
	// now we go through each of the detected major chords, calculate its consonants, and determine how many of its consonants are present in the song.
	$scores = array();
	foreach ( $majors as $key => $count )
	{
		$scores[$key] = 0;
		$consonants = get_consonants(name_to_key($key));
		if ( isset($majors[key_to_name($consonants['fourth'])]) )
			$scores[$key] += 2;
		if ( isset($majors[key_to_name($consonants['fifth'])]) )
			$scores[$key] += 2;
		if ( isset($majors[key_to_name($consonants['minors'][0])]) )
			$scores[$key] += 1;
		if ( isset($majors[key_to_name($consonants['minors'][1])]) )
			$scores[$key] += 2;
		if ( isset($majors[key_to_name($consonants['minors'][2])]) )
			$scores[$key] += 1;
	}
	$winner_val = -1;
	$winner_key = '';
	foreach ( $scores as $key => $score )
	{
		if ( $score > $winner_val )
		{
			$winner_val = $score;
			$winner_key = $key;
		}
	}
	$winner_key = key_to_name(name_to_key($winner_key), $sharp_or_flat);
	return $winner_key;
}

function key_to_name($root_key, $accidental = ACC_SHARP)
{
	switch($root_key)
	{
		case KEY_C:
			return 'C';
		case KEY_D:
			return 'D';
		case KEY_E:
			return 'E';
		case KEY_F:
			return 'F';
		case KEY_G:
			return 'G';
		case KEY_A:
			return 'A';
		case KEY_B:
			return 'B';
		case KEY_C_SHARP:
			return $accidental == ACC_FLAT ? 'Db' : 'C#';
		case KEY_E_FLAT:
			return $accidental == ACC_FLAT ? 'Eb' : 'D#';
		case KEY_F_SHARP:
			return $accidental == ACC_FLAT ? 'Gb' : 'F#';
		case KEY_G_SHARP:
			return $accidental == ACC_FLAT ? 'Ab' : 'G#';
		case KEY_B_FLAT:
			return $accidental == ACC_FLAT ? 'Bb' : 'A#';
		default:
			return false;
	}
}

function name_to_key($name)
{
	switch($name)
	{
		case 'C': return KEY_C;
		case 'D': return KEY_D;
		case 'E': return KEY_E;
		case 'F': return KEY_F;
		case 'G': return KEY_G;
		case 'A': return KEY_A;
		case 'B': return KEY_B;
		case 'C#': case 'Db': return KEY_C_SHARP;
		case 'D#': case 'Eb': return KEY_E_FLAT;
		case 'F#': case 'Gb': return KEY_F_SHARP;
		case 'G#': case 'Ab': return KEY_G_SHARP;
		case 'A#': case 'Bb': return KEY_B_FLAT;
		default: return false;
	}
}

function prettify_accidentals($chord)
{
	if ( count(explode('/', $chord)) > 1 )
	{
		list($upper, $lower) = explode('/', $chord);
		return prettify_accidentals($upper) . '/' . prettify_accidentals($lower);
	}
	
	if ( strlen($chord) < 2 )
		return $chord;
	
	if ( $chord{1} == 'b' )
	{
		$chord = $chord{0} . '&flat;' . substr($chord, 2);
	}
	else if ( $chord{1} == '#' )
	{
		$chord = $chord{0} . '&sharp;' . substr($chord, 2);
	}
	return $chord;
}

function transpose_chord($chord, $increment, $accidental = false)
{
	global $circle_of_fifths;
	
	if ( count(explode('/', $chord)) > 1 )
	{
		list($upper, $lower) = explode('/', $chord);
		return transpose_chord($upper, $increment, $accidental) . '/' . transpose_chord($lower, $increment, $accidental);
	}
	// shave off any wacky things we're doing to the chord (minor, seventh, etc.)
	preg_match('/((?:m?7?|2|add9|sus4|[Mm]aj[79])?)$/', $chord, $match);
	// find base chord
	if ( !empty($match[1]) )
		$chord = str_replace($match[1], '', $chord);
	// what's our accidental? allow it to be specified, and autodetect if it isn't
	if ( !$accidental )
		$accidental = strstr($chord, '#') ? ACC_SHARP : ACC_FLAT;
	// convert to numeric value
	$key = name_to_key($chord);
	if ( $key === false )
		// should never happen
		return "[TRANSPOSITION FAILED: " . $chord . $match[1] . "]";
	// transpose
	$key = (($key + $increment) + count($circle_of_fifths)) % count($circle_of_fifths);
	// return result
	$kname = key_to_name($key, $accidental);
	if ( !$kname )
		// again, should never happen
		return "[TRANSPOSITION FAILED: " . $chord . $match[1] . " + $increment (-&gt;$key)]";
	$result = $kname . $match[1];
	// echo "$chord{$match[1]} + $increment = $result<br />";
	return $result;
}

function halftone_process_tags(&$text)
{
	static $css_added = false;
	if ( !$css_added )
	{
		global $template;
		$template->preload_js(array('jquery', 'jquery-ui'));
		$template->add_header('
			<style type="text/css">
				h1.halftone-title {
					page-break-before: always;
				}
				span.halftone-line {
					display: block;
					padding-top: 10pt;
					position: relative; /* allows the absolute positioning in chords to work */
				}
				span.halftone-chord {
					position: absolute;
					top: 0pt;
					color: rgb(27, 104, 184);
				}
				span.halftone-chord.sequential {
					padding-left: 20pt;
				}
				div.halftone-key-select {
					float: right;
				}
			</style>
			<script type="text/javascript">
				addOnloadHook(function()
					{
						$("select.halftone-key").change(function()
							{
								var me = this;
								var src = $(this.parentNode.parentNode).attr("halftone:src");
								ajaxPost(makeUrlNS("Special", "HalftoneRender", "transpose=" + $(this).val()) + "&tokey=" + $("option:selected", this).attr("halftone:abs"), "src=" + encodeURIComponent(src), function(ajax)
									{
										if ( ajax.readyState == 4 && ajax.status == 200 )
										{
											var $songbody = $("div.halftone-song", me.parentNode.parentNode);
											$songbody.html(ajax.responseText);
										}
									});
							});
					});
			</script>
			');
		$css_added = true;
	}
	if ( preg_match_all('/<halftone(.*?)>(.+?)<\/halftone>/s', $text, $matches) )
	{
		foreach ( $matches[0] as $i => $whole_match )
		{
			$attribs = decodeTagAttributes($matches[1][$i]);
			$song_title = isset($attribs['title']) ? $attribs['title'] : 'Untitled song';
			$chord_list = array();
			$inner = trim($matches[2][$i]);
			$song = halftone_render_body($inner, $chord_list);
			$src = base64_encode($whole_match);
			$key = name_to_key(detect_key($chord_list));
			$select = '<select class="halftone-key">';
			for ( $i = 0; $i < 12; $i++ )
			{
				$label = in_array($i, array(KEY_C_SHARP, KEY_E_FLAT, KEY_F_SHARP, KEY_G_SHARP, KEY_B_FLAT)) ? sprintf("%s/%s", key_to_name($i, ACC_SHARP), key_to_name($i, ACC_FLAT)) : key_to_name($i);
				$label = prettify_accidentals($label);
				$sel = $key == $i ? ' selected="selected"' : '';
				$select .= sprintf("<option%s value=\"%d\" halftone:abs=\"%d\">%s</option>", $sel, $i - $key, $i, $label);
			}
			$select .= '</select>';
			$text = str_replace_once($whole_match, "<div class=\"halftone\" halftone:src=\"$src\"><div class=\"halftone-key-select\">$select</div><h1 class=\"halftone-title\">$song_title</h1>\n\n<div class=\"halftone-song\">\n" . $song . "</div></div>", $text);
		}
	}
}

function halftone_render_body($inner, &$chord_list, $inkey = false)
{
	global $accidentals;
	$song = '';
	$chord_list = array();
	$transpose = isset($_GET['transpose']) ? intval($_GET['transpose']) : 0;
	$transpose_accidental = $inkey ? $accidentals[$inkey] : false;
	foreach ( explode("\n", $inner) as $line )
	{
		$chordline = false;
		$chords_regex = '/(\((?:[A-G][#b]?(?:m?7?|2|add9|sus4|[Mm]aj[79])?(?:\/[A-G][#b]?)?)\))/';
		$line_split = preg_split($chords_regex, $line, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ( preg_match_all($chords_regex, $line, $chords) )
		{
			// this is a line with lyrics + chords
			// echo out the line, adding spans around chords. here is where we also do transposition
			// (if requested) and 
			$line_final = array();
			$last_was_chord = false;
			foreach ( $line_split as $entry )
			{
				if ( preg_match($chords_regex, $entry) )
				{
					if ( $last_was_chord )
					{
						while ( !($pop = array_pop($line_final)) );
						$new_entry = preg_replace('#</span>$#', '', $pop);
						$new_entry .= str_repeat('&nbsp;', 4);
						$new_entry .= prettify_accidentals($chord_list[] = transpose_chord(trim($entry, '()'), $transpose, $transpose_accidental)) . '</span>';
						$line_final[] = $new_entry;
					}
					else
					{
						$line_final[] = '<span class="halftone-chord">' . prettify_accidentals($chord_list[] = transpose_chord(trim($entry, '()'), $transpose, $transpose_accidental)) . '</span>';
					}
					$last_was_chord = true;
				}
				else
				{
					if ( trim($entry) != "" )
					{
						$last_was_chord = false;
						$line_final[] = $entry;
					}
				}
			}
			$song .= '<span class="halftone-line">' . implode("", $line_final) . "</span>\n";
		}
		else if ( preg_match('/^=\s*(.+?)\s*=$/', $line, $match) )
		{
			$song .= "== {$match[1]} ==\n";
		} 
		else if ( trim($line) == '' )
		{
			continue;
		}
		else
		{
			$song .= "$line<br />\n";
		}
	}
	return $song;
}

function page_Special_HalftoneRender()
{
	global $accidentals;
	$text = isset($_POST['src']) ? base64_decode($_POST['src']) : '';
	if ( preg_match('/<halftone(.*?)>(.+?)<\/halftone>/s', $text, $match) )
	{
		require_once(ENANO_ROOT . '/includes/wikiformat.php');
		$carp = new Carpenter();
		$carp->exclusive_rule('heading');
		$tokey = isset($_GET['tokey']) ? intval($_GET['tokey']) : false;
		echo $carp->render(halftone_render_body($match[2], $chord_list, $tokey));
	}
}