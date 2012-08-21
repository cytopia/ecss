<?php
/**
 * Extended CSS (ECSS)
 * Copyright (C) 2012-2012 Patrick Plocke.
 *
 * ECSS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ECSS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ECSS. If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright	Copyright 2012-2012, Patrick Plocke <patrick[dot]plocke[at]mailbox[dot]tu-berlin[dot]de>
 * @link		https://github.com/lockdoc/ecss
 * @package		sweany.www
 * @author		Patrick Plocke <patrick[dot]plocke[at]mailbox[dot]tu-berlin[dot]de>
 * @license		GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @version		0.7 2012-08-21 23:37
 *
 * Extended CSS is a preprocessor for a normal css file that will add inheritance
 * as a language construct.
 *
 * ECSS is a side-product by sweany (https://github.com/lockdoc/sweany) and comes
 * under the same license.
 *
 * Note about the code:
 * ---------------------
 * I do know that the code is written kind of inefficiently, but as the whole preprocessing
 * is only for development stage and you will take the preprocessed css file anyway,
 * it does not matter. Apart from the above, writing the code this way is more understandable
 * (at least for me).
 */

/*******************************************************************************************
 *
 *                           OPTIONS
 *
 *******************************************************************************************/

 /**
  *  Options via $_GET
  *
  *  file=filename.css
  *    Specifies the css file to preprocess
  *
  *  compressed
  *    If appended, the preprocessor will produce
  *    compressed code (removed lines, spaces and tabs)
  *
  */
 
 
 
 /**
  * Which newline character to use to break
  * lines in preprocessed CSS file?
  *
  * \n, \r, \n\r...
  */
$new_line = "\n";


/**
 * Specify the left intendation inside
 * CSS class, ids and/or tag defines.
 * Used for non-compressed CSS code generation
 *
 * Default: "\t"
 */
$intend	= "\t";





/*******************************************************************************************
 *
 *                           HELPER FUNCTIONS
 *
 *******************************************************************************************/

 /**
  * nice print_r version
  *
  *
  * @param	mixed
  * @return	String
  */
function _debug($arr){echo '<pre>';print_r($arr);echo '</pre>';}


/**
 * Adds a certain amount of whitespaces
 * to a string.
 */
function _addSpace($num){$space='';for ($i=0; $i<$num; $i++){$space.=' ';}return $space;}


/**
 * Read the contents of a file.
 * This can either be locally, 
 * or remotely.
 *
 * @param	string		$file
 * @return	string|bool	Content or false on Error
 */
function _loadFile($file)
{
	if ( !($fp = fopen($file, "r")) )
	{
		return false;
	}
	$content = '';
	while ( !feof($fp) )
	{
		$content .= fread($fp, 8192);
	}
	fclose($fp);
	return $content;
}


/*******************************************************************************************
 *
 *                           CORE FUNCTIONS
 *
 *******************************************************************************************/

/**
 *
 * Remove comments
 *
 * @param	String	raw CSS String
 * @return	String	raw CSS String (without comments)
 */
function _removeComments($raw_css)
{
	$find_start	= preg_quote('/*');
	$find_end	= preg_quote('*/');
	$rep_start	= '';
	$rep_end	= '';
	$between	= ''; //'$i'; by removing $i, we make sure that anything in between rep_start and rep_end is removed
	$raw_css	= preg_replace('#\\'.$find_start.'(.*)\\'.$find_end.'#isU', $rep_start.$between.$rep_end, $raw_css);
	return $raw_css;	
}



/**
 * Get all CSS Elements that do inherit other elements via 'extend'
 * 
 * It will also take overwriting into account:
 * If a class or property (inside a class) exists twice or more,
 * it will be overwritten by the last occurance (just like in the css file)
 *
 * @param	String	$raw_css	Content of css file to preprocess
 * @return	mixed[] Elements
 *
 * $elements = array(
 *   'element_name'	=> array(
 *			0	=> 'extend element 1',
 *			1	=> 'extend element 2',
 *	  )
 *	);
 */
function extractExtentElements($raw_css)
{
	$string		= trim($raw_css);
	$tmpArr		= explode('}', $string);
	$index		= 0;
	$classes	= array();
	
	// phase 1 (get arrayed values)
	foreach ($tmpArr as $element)
	{	
		if ( strpos($element, '{') !== false )
		{
			// seperate classes/id's
			$seperate = explode('{', $element);
			
			if ( isset($seperate[0]) && isset($seperate[1]) )
			{
				$head = trim($seperate[0]);
				
				if ( strpos($head, 'extends') !== false )
				{
					$seperate	= explode('extends', $head, 2);
					$class		= trim($seperate[0]);
					$extends	= trim($seperate[1]);
					$extendArr	= array();
					
					if ( strpos($extends, ',') !== false )
					{
						$extends = explode(',', $extends);
						foreach ($extends as $ext)
						{
							$extendArr[] = trim($ext);
						}
					}
					else
					{
						$extendArr[] = array(trim($extends));
					}
					$classes[$class] = $extendArr;
				}
			}
		}
	}
	return $classes;

}

/**
 * Get all CSS Elements and eliminate 'extends'
 * to have a value/data array
 * 
 * It will also take overwriting into account:
 * If a class or property (inside a class) exists twice or more,
 * it will be overwritten by the last occurance (just like in the css file)
 *
 * @param	String	$raw_css	Content of css file to preprocess
 * @return	mixed[] Elements
 *
 * $elements = array(
 *   'element_name'	=> array(
 *			'property1'	=> 'value',
 *			'property2'	=> 'value',
 *	  )
 *	);
 */
function extractCSSElements($raw_css)
{
	$string		= trim($raw_css);
	$tmpArr		= explode('}', $string);
	
	$phase1Arr	= array();
	$phase2Arr	= array();
	$phase3Arr	= array();
	$phase4Arr	= array();	

	$index		= 0;
	
	// phase 1 (get arrayed values)
	foreach ($tmpArr as $element)
	{	
		if ( strpos($element, '{') !== false )
		{
			// seperate classes/id's
			$seperate = explode('{', $element);
			
			if ( isset($seperate[0]) && isset($seperate[1]) )
			{
				$phase1Arr[$index]['head'] = trim($seperate[0]);
				$phase1Arr[$index]['body'] = trim($seperate[1]);
			}
			$index++;
		}
	}
	
	// phase 2 (remove 'extends' classes)
	$index = 0;
	foreach ($phase1Arr as $element)
	{
		if ( strpos($element['head'], 'extends') !== false)
		{
			$seperate	= explode('extends', $element['head']);
			$head		= $seperate[0];
		}
		else
		{
			$head		= $element['head'];
		}
		$phase2Arr[$index]['head'] = trim($head);
		$phase2Arr[$index]['body'] = trim($element['body']);
		$index++;
	}
	
	// phase 3 (extract multiple element definitions: e.g.: a,p { color:green; } )
	$index = 0;
	foreach ($phase2Arr as $element)
	{
		if ( strpos($element['head'], ',') !== false)
		{
			$seperate	= explode(',', $element['head']);
			foreach ($seperate as $elem)
			{
				$phase3Arr[$index]['head'] = trim($elem);
				$phase3Arr[$index]['body'] = $element['body'];
				$index++;
			}
		}
		else
		{
			$phase3Arr[$index]['head'] = $element['head'];
			$phase3Arr[$index]['body'] = $element['body'];
			$index++;
		}
	}
	// phase 4 (seperate property values in different sub array keys)
	$index = 0;
	foreach ($phase3Arr as $element)
	{
		$name = $element['head'];
		$phase4Arr[$name] = array();
		
		if ( strpos($element['body'], ';') !== false)
		{
			$properties	= explode(';', $element['body']);
			foreach ($properties as $property)
			{
				// split properties into prop => value
				if ( strpos($property, ':') !== false )
				{
					$proVal = explode(':', $property, 2);	// split only into first and second element and append all others with a ':' to the second element
					$phase4Arr[$name][trim($proVal[0])] = trim($proVal[1]);
				}
			}
		}
		$index++;
	}	
	return $phase4Arr;
} 
 
 

 
/**
 * Preprocess the CSS Code (with extends-keywords) and generate
 * normal working CSS Code.
 *
 * Note:
 * Add inherited elements at the beginning
 * of the arrays,
 * so they can be overwrittin
 * by local values if they exists.
 *
 * @param	mixed[]
 * @param	mixed[]
 * @return	mixed[]
 */
function preprocessCss($cssElementArr, $extendElementArr)
{
	$cssArray	= array();
	$index		= 0;
	
	foreach ($cssElementArr as $element => $properties)
	{
		// add inherited elements
		if ( array_key_exists($element, $extendElementArr) )
		{
			foreach ( $extendElementArr[$element] as $inheritedClass )
			{
				if ( !isset($cssArray[$element]) )
				{
					// prevent errors if extending an unknown class/id/element
					if ( array_key_exists($inheritedClass, $cssElementArr) )
					{
						$cssArray[$element] = $cssElementArr[$inheritedClass];
					}
				}
				else
				{
					// prevent errors if extending an unknown class/id/element
					if ( array_key_exists($inheritedClass, $cssElementArr) )
					{
						$cssArray[$element] = array_merge($cssArray[$element], $cssElementArr[$inheritedClass]);
					}
				}
			}
		}
		
		// normal css
		if ( !isset($cssArray[$element]) )
		{
			$cssArray[$element] = $cssElementArr[$element];
		}
		else
		{
			$cssArray[$element] = array_merge($cssArray[$element], $cssElementArr[$element]);
		}		
	}
	return $cssArray;
}


/**
 * Output the newly generated css to the screen
 * with either readable or compressed form
 *
 * @param	mixed[]	preprocessed array
 * @param	boolean compressed|readable output
 */
function outputToScreen($cssPreprocessedArr, $compressed = false)
{
	global $new_line;
	global $intend;
	
	header("Content-type: text/css", true);
	if ( $compressed )
	{
		foreach ($cssPreprocessedArr as $element => $properties)
		{
			echo $element.'{';
			foreach ($properties as $property => $value)
			{
				echo $property.':'.$value.';';
			}
			echo '}';
		}	
	}
	else // compressed
	{
		foreach ($cssPreprocessedArr as $element => $properties)
		{
			echo $element.' {'.$new_line;
			foreach ($properties as $property => $value)
			{
				$len	= strlen($property);
				$space	= _addSpace(20-$len);
				echo $intend.$property.' :'.$space.$value.';'.$new_line;
			}
			echo '}'.$new_line;
		}
	}
}




/*******************************************************************************************
 *
 *                           MAIN ENTRY POINT
 *
 *******************************************************************************************/


if ( !isset($_GET['file']) || !strlen($_GET['file']) )
{
	echo 'you have to call me like this:<br/>'.$_SERVER["SCRIPT_NAME"].'?file=CSS_FILE_TO_PREPROCESS.css<br/>or<br/>';
	echo 'you have to call me like this:<br/>'.$_SERVER["SCRIPT_NAME"].'?file=CSS_FILE_TO_PREPROCESS&compressed.css';
	exit;
}

if ( !($raw_css = _loadFile($_GET['file'])) )
{
	echo 'The specified CSS file: <strong style="color:blue;">'.$_GET['file'].'</strong> does not exist.';
	exit;
}


$raw_css			= _removeComments($raw_css);
$cssElementArr 		= extractCSSElements($raw_css);
$extendElementArr	= extractExtentElements($raw_css);
$cssPreprocessedArr	= preprocessCss($cssElementArr, $extendElementArr);

outputToScreen($cssPreprocessedArr, isset($_GET['compressed']));
exit;

