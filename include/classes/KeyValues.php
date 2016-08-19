<?php
// kvwriteFile - Write KeyValues array to file
function kvwriteFile($file, $arr) {
	$contents = kvwrite($arr);
	$fh = fopen($file, 'w');
	fwrite($fh, $contents);
	fclose($fh);
}
// kvwrite - Turn an array into KeyValues
function kvwrite($arr,$tier=0,$tree=array()) {
	$str = "";
	kvwriteSegment($str, $arr,$tier,$tree);
	return $str;
}
// kvwriteSegment - Create a section of a KeyValues file from array
function kvwriteSegment(&$str, $arr, $tier = 0,$tree=array()) {
	global $ordered_fields;
	$indent = str_repeat(chr(9), $tier);
	// TODO check for a certain key to keep it in the same tier instead of going into the next?
	foreach ($arr as $key => $value) {
		if (is_array($value)) {
			$tree[$tier] = $key;
			$key = '"' . $key . '"';
			$str .= $indent . $key  . "\n" . $indent. "{\n";
			$path=implode("/",$tree);
			// If this item is an ordered array, deserialize it
			if ((matchTheaterPath($path,$ordered_fields)) && (is_numeric_array(array_keys($value)))) {
				foreach ($value as $idx=>$item) {
					foreach ($item as $k => $v) {
						$str .= chr(9) . $indent . QuoteAndTabKeyValue($k,$v) . "\n";
					}
				}
			} else {
// 				echo "Array<br>\n";
				kvwriteSegment($str, $value, $tier+1,$tree);
			}
			$str .= $indent . "}\n";
			unset($tree[$tier]);
		} else {
// 			echo "String<br>\n";
			$str .= $indent . QuoteAndTabKeyValue($key,$value) . "\n";
		}
	}
	return $str;
}
// This function displays a spaced key value pair, quoted in aligned columns
function QuoteAndTabKeyValue($key,$val,$tabs=8) {

	$tabsize=4;
	$len = strlen($key)+2;
	$mod = ($len % $tabsize);
	$diff = floor($tabs - ($len / $tabsize))+($mod>0);
	$sep = str_repeat("\t",$diff);

	return "\"{$key}\"\t{$sep}\"{$val}\"";// {$len} {$mod} {$diff}";
	
}
/*

*/
function TypecastValue($val) {
	if (is_numeric($val)) {
		if (strpos($val,'.') !== false) {
			$val = (float)$val;
		} else {
			$val = (int)$val;
		}
	}
	return($val);
}
function filterTheaterPath ($val) {
	return ($val[0] != '?' && $val != '');
}

// Take two lists of paths to match. Return the first element from matches that matches the first path that finds one.
function matchTheaterPath($paths, $matches) {
	if (!is_array($paths)) {
		$paths=array($paths);
	}
	if (!is_array($matches)) {
		$matches=array($matches);
	}
	// Loop over each path to match
	foreach ($paths as $path) {
		//$path_parts = array_filter(explode("/",$path), 'filterTheaterPath');
		$path_parts = explode("/",$path);
		// Check each match for this path
		foreach ($matches as $match) {
			$match_parts = explode("/",$match);
			//$match_parts = array_filter(explode("/",$match), 'filterTheaterPath');
			// If the number of elements differs, this cannot be a match.
			// NB: To match subkeys of a tree, use multiple wildcards like /theater/player_templates/*/*/*
			if (count($match_parts) != count($path_parts)) {
				continue;
			}
			// Compare each element of both paths
			foreach ($match_parts as $mid=>$mpart) {
				// Strip conditionals from each element to compare
				$mpart = array_shift(explode("?",$mpart));
				$ppart = array_shift(explode("?",$path_parts[$mid]));
				// If this element does not match, and neither element is a wildcard, stop checking this match
				if (($mpart != $ppart) && ($ppart != '*') && ($mpart != '*')) {
					continue 2;
				}
				// If this is the last element to check, then the match is returned
				if (($mid+1) == count($match_parts)) {
					return $match;
				}
			}
		}
	}
	// Nothing matched, return false
	return false;
}

// parseKeyValues - Take a string of KeyValues data and parse it into an array
function parseKeyValues($KVString,$fixquotes=true,$debug=false)
{
	global $ordered_fields,$theater_conditions,$allow_duplicates_fields;
	// Escape all non-quoted values
	if ($fixquotes)
		$KVString = preg_replace('/^(\s*)([a-zA-Z]+)/m','${1}"${2}"',$KVString);
	$KVString = preg_replace('/^(\s+)/m','',$KVString);
	$KVLines = preg_split('/\n|\r\n?/', $KVString);
	$len = strlen($KVString);
	// if ($debug) $len = 2098;

	$stack = array();

	$isInQuote = false;
	$quoteKey = "";
	$quoteValue = "";
	$quoteWhat = "key";

	$lastKey = "";
	$lastPath = "";
	$lastValue = "";
	$lastLine = "";

	$keys = array();
	$comments = array();
	$commentLines=1;

	$ptr = &$stack;
	$c="";
	$line = 1;

	$parents = array(&$ptr);
	$tree = array();
	$path="";
	$sequential = '';
	$sequential_path = '';
	$conditional='';
	$conditional_path='';
	$allowdupes='';
	for ($i=0; $i<$len; $i++)
	{
		$l = $c;
		$c = $KVString[$i]; // current char
		switch ($c)
		{
			case "\"":
				$commentLines=1;
				if ($isInQuote) // so we are CLOSING key or value
				{
					// EDIT: Use quoteWhat as a qualifier rather than quoteValue in case we have a "" value
					if (strlen($quoteKey) && ($quoteWhat == "value"))
					{
						// If this is a top-level child of a conditional, append condition to quoteKey.
						if (($path == $conditional_path) && ($conditional)) {
							//var_dump("This is it", $conditional, $conditional_path, $quoteKey, $tree);
							$quoteKey = "{$quoteKey}{$conditional}";
						}

						if ($sequential) {
							if (!$allowdupes) {
								// Check to make sure this value does not already exist
								if (is_array($ptr)) {
									foreach ($ptr as $item) {
										if (isset($item[$quoteKey])) {
											if ($item[$quoteKey] == $quoteValue) {
												$quoteValue = '';
											}
										}
									}
								}
							}

							if ($quoteValue) {
								$ptr[] = array($quoteKey => TypecastValue($quoteValue));
							}
						} else {
							// If this value is already set, make it an array
							if (isset($ptr[$quoteKey])) {
								// If the item is not already an array, make it one
								if (!is_array($ptr[$quoteKey])) {
									$ptr[$quoteKey] = array($ptr[$quoteKey]);
								}
								// Add this value to the end of the array
								$ptr[$quoteKey][] = TypecastValue($quoteValue);
							} else {
								// Set the value otherwise
								$ptr[$quoteKey] = TypecastValue($quoteValue);
							}
						}
						$lastLine = $line;
						$lastPath = "{$path}/${quoteKey}";
						$lastKey = $quoteKey;
						$quoteKey = "";
						$quoteValue = "";
					}
					// Toggle key or value tracking
					$quoteWhat = ($quoteWhat == "key") ? "value" : "key";
				}
				$isInQuote = !$isInQuote;
				break;
			// Start new section
			case "{":
				$commentLines=1;
				if (strlen($quoteKey)) {
					// If this key begins with a "?", process it as a conditional
					// NB: This does not handle nested conditionals. It will shit itself.
					if (substr($quoteKey,0,1) == '?') {
						$conditional=$quoteKey;
						$theater_conditions[$conditional][] = $conditional_path = $path;
					} else {
						// If this is a top level child of a theater conditional, append the conditional to the key name.
						if ((implode("/",$tree) == $conditional_path) && ($conditional)) {
							//var_dump("This is it", $conditional, $conditional_path, $quoteKey, $tree);
							$quoteKey = "{$quoteKey}{$conditional}";
						}
						// Update path in tree
						// Add key to tree
						$tree[] = $quoteKey;
						// Update path
						$path = implode("/",$tree);
//						if ($conditional) {
//							$theater_conditions[$conditional_path][$conditional][] = $path;
//						}
						$sequential = matchTheaterPath($path,$ordered_fields);
						if ((!$sequential_path) && ($sequential)) {
							$sequential_path = $path;
						}
						if (!$allowdupes) {
							$allowdupes = matchTheaterPath($path,$allow_duplicates_fields);
						}
						// Update parents array with current pointer in the new path location
						$parents[$path] = &$ptr;

						// If the object already exists, create an array of objects
						if (isset($ptr[$quoteKey])) {
							// Get all the keys, this assumes that the data will have non-numeric keys.
							$keys = implode('',array_keys($ptr[$quoteKey]));
							// So when we see non-numeric keys, we push the existing data into an array of itself before appending the next object.
							if (!is_numeric($keys)) {
								$ptr[$quoteKey] = array($ptr[$quoteKey]);
							}
							// Move the pointer to a new array under the key
							$ptr = &$ptr[$quoteKey][];
						} else {
							// Just put the object here if there is no existing object
							$ptr = &$ptr[$quoteKey];
						}
						$lastPath = "{$path}/${quoteKey}";
						$lastKey = $quoteKey;
					}
					$quoteKey = "";
					$quoteWhat = "key";
				}
				$lastLine = $line;
				break;
			// End of section
			case "}":
				$commentLines=1;
				// Move pointer back to the parent
				if ($conditional_path != $path) {
					$sequential='';
					if ($path == $allowdupes) {
						$allowdupes='';
					}
					if ($sequential) {
						if ($path == $sequential_path) {
							$sequential_path='';
						}

					}
					$ptr = &$parents[$path];
					// Take last element off tree as we back out
					array_pop($tree);
					// Update path now that we have backed out
					$path = implode("/",$tree);
				} else {
					$conditional = '';
					$conditional_path = '';
				}
				$lastLine = $line;
				break;
				
			case "\t":
				break;
			case "/":
				// Comment "// " or "/*"
				if (($KVString[$i+1] == "/") || ($KVString[$i+1] == "*"))
				{
					$comment = "";
					// Get comment type
					$ctype = $KVString[$i+1];
					while($i < $len) {
						// If type is "// " stop processing at newline
						if (($ctype == '/') && ($KVString[$i+1] == "\n")) {
// 							$i+=2;
							break;
						}
						// If type is "/*" stop processing at "*/"
						if (($ctype == '*') && ($KVString[$i+1] == "*") && ($KVString[$i+2] == "/")) {
							$i+=2;
							$comment.="*/";
							break;
						}
						$comment.=$KVString[$i];
						$i++;
					}
					$comment = trim($comment);
					// Was this comment inline, or after the last item we processed?
					$where = ($lastLine == $line) ? 'inline' : 'newline';
					// If last line was also a comment, see if we can merge into a multi-line comment
					// Use the commentLines to see how far back this started
					$lcl = ($line-$commentLines);
					if (isset($comments[$lcl])) {
						$lc = $comments[$lcl];
						if ($lc['path'] == $lastPath) {
							$comments[$lcl]['line_text'].="\n{$KVLines[$line-1]}";
							$comments[$lcl]['comment'].="\n{$comment}";
							$comment='';
							$commentLines++;
						}
					}
					// If we have a comment, add it to the list
					if ($comment) {
						$comments[$line] = array('path' => $lastPath, 'where' => $where, 'line' => $line, 'line_text' => $KVLines[$line-1], 'comment' => $comment);
					}
					continue;
				}
			default:
				if ($isInQuote) {
					if ($quoteWhat == "key")
						$quoteKey .= $c;
					else
						$quoteValue .= $c;
				}
				if ($c == "\n")
					$line++;
		}
	}
	
	if ($debug) {
		echo "<hr><pre>";
		var_dump("stack: ",$stack);
	}
	return $stack;
}

// prettyPrint - Print JSON with proper indents and formatting
function prettyPrint( $json )
{
	$result = '';
	$level = 0;
	$in_quotes = false;
	$in_escape = false;
	$ends_line_level = NULL;
	$json_length = strlen( $json );

	for( $i = 0; $i < $json_length; $i++ ) {
		$char = $json[$i];
		$new_line_level = NULL;
		$post = "";
		if( $ends_line_level !== NULL ) {
			$new_line_level = $ends_line_level;
			$ends_line_level = NULL;
		}
		if ( $in_escape ) {
			$in_escape = false;
		} else if( $char === '"' ) {
			$in_quotes = !$in_quotes;
		} else if( ! $in_quotes ) {
			switch( $char ) {
				case '}':
				case ']':
					$level--;
					$ends_line_level = NULL;
					$new_line_level = $level;
					break;
				case '{':
				case '[':
					$level++;
					case ',':
					$ends_line_level = $level;
					break;

				case ':':
					$post = " ";
					break;

				case " ":
				case "\t":
				case "\n":
				case "\r":
					$char = "";
					$ends_line_level = $new_line_level;
					$new_line_level = NULL;
					break;
			}
		} else if ( $char === '\\' ) {
			$in_escape = true;
		}
		if( $new_line_level !== NULL ) {
			$result .= "\n".str_repeat( "\t", $new_line_level );
		}
		$result .= $char.$post;
	}
	return $result;
}
