<?php

abstract class XXX_TemplateParser
{
	/*
	
	
	public static $delimiters = array('[', ']');
	
	public static $keywords = array
	(
		'begin' => 'START:',
		'end' => 'END:',
		'template' => 'TEMPLATE:',
		'include' => 'INCLUDE:',
		'variable' => '$'
	);	
	
	*/
	
	// TODO Make sure the patterns don't match different opening/closing brackets....
	
	public static $delimiters = array
	(
		array('[', ']'),
		array('{', '}'),
		array('<!--', '-->')
	);
	
	public static $keywords = array
	(
		'begin' => array
		(
			'BEGIN:',
			'START:'
		),
		'end' => 'END:',
		'template' => array
		(
			'TEMPLATE:'
		),
		'include' => 'INCLUDE:',
		'variable' => array
		(
			'$',
			'VARIABLE:'
		)
	);
	
	public static $placeHolders = array
	(
		'block' => '__BLOCK__',
		'template' => '__TEMPLATE__'
	);
	
	public static $patterns = array();
		
	public static function setDelimiter ($begin, $end)
	{
		$result = false;
		
		if (XXX_Type::isValue($begin) && XXX_Type::isValue($end))
		{
			self::$delimiters = array($begin, $end);
			$result = true;
		}
		
		return $result;
	}
	
	public static function addDelimiter ($begin, $end)
	{
		$result = false;
		
		if (XXX_Type::isValue($begin) && XXX_Type::isValue($end))
		{
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				self::$delimiters[] = array($begin, $end);
			}
			else
			{
				$oldValue = self::$delimiters;
				self::$delimiters = array();
				self::$delimiters[] = $oldValue;
				self::$delimiters[] = array($begin, $end);				
			}
			$result = true;
		}
		
		return $result;
	}
	
	public static function getDelimiters ()
	{
		return self::$delimiters;
	}
	
	public static function getKeywords ()
	{
		return self::$keywords;
	}
	
	public static function getPatterns ()
	{
		return self::$patterns;
	}
	
	public static function isValidKeyword ($keyword, $keyWordType = '')
	{
		$result = false;
		
		$validKeywordsForType = self::$keywords[$keyWordType];
		
		if
		(
			$validKeywordsForType
		 	&&
			(
				(XXX_Type::isString($validKeywordsForType) && $keyword == $validKeywordsForType)
				||
				(XXX_Type::isArray($validKeywordsForType) && XXX_Array::hasValue($validKeywordsForType, $keyword))
			)
		)
		{
			$result = true;
		}
		
		return $result;
	}
	
	public static function getAVariableKeyword ()
	{
		if (XXX_Type::isArray(self::$keywords['variable']))
		{
			$variableKeyword = self::$keywords['variable'][0];
		}
		else
		{
			$variableKeyword = self::$keywords['variable'];
		}
		
		return $variableKeyword;
	}
	
	public static function getADelimiter ()
	{
		if (XXX_Array::getDeepestLevel(self::$delimiters) > 1)
		{
			$delimiter = self::$delimiters[0];
		}
		else
		{
			$delimiter = self::$delimiters;
		}
		
		return $delimiter;
	}
	
	
	public static function parseTemplateFileReferences ($fileContent = '')
	{
		$templateFiles = array();
				
		$matches = XXX_String_Pattern::getMatches($fileContent, self::$patterns['template'][0], self::$patterns['template'][1]);
		
		if (XXX_Type::isArray($matches) && XXX_Array::getFirstLevelItemTotal($matches[0]))
		{
			for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal($matches[0]); $i < $iEnd; ++$i)
			{
				$templateFile = array
				(
					'fullString' => $matches[0][$i],
					'pattern' => XXX_String_Pattern::escape($matches[0][$i]),
					'template' => ''
				);
				
				if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
				{
					for ($j = 0, $jEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $j < $jEnd; ++$j)
					{
						$offset = 1 + ($j * $jEnd);
													
						if (XXX_Type::isValue($matches[$offset][$i]))
						{							
							if (XXX_Type::isValue($matches[$offset + 1][$i]))
							{
								$templateFile['template'] = $matches[$offset + 1][$i];
							}
							else
							{
								$templateFile['template'] = $matches[$offset + 2][$i];
							}
							break;
						}
					}
				}
				else
				{
					if (XXX_Type::isValue($matches[1 + 1][$i]))
					{
						$templateFile['template'] = $matches[1 + 1][$i];
					}
					else
					{
						$templateFile['template'] = $matches[1 + 2][$i];
					}
				}
														
				$templateFiles[] = $templateFile;
			}
		}
		
		return $templateFiles;
	}
	
	public static function parseFileVariableReference ($blockName = '', $blockContent = '', $fileVariableType = 'template')
	{
		$fileVariableType = XXX_Default::toOption($fileVariableType, array('template', 'include'), 'template');
		
		$parsedMatches = array();
				
		$matches = XXX_String_Pattern::getMatches($blockContent, self::$patterns[$fileVariableType . 'Variable'][0], self::$patterns[$fileVariableType . 'Variable'][1]);
		
		foreach ($matches[0] as $key => $value)
		{			
			$parsedMatch = array
			(
				'fullString' => $matches[0][$key],
				'name' => '',
				'blockName' => $blockName
			);
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					for ($j = 0, $jEnd = $iEnd; $j < $jEnd; ++$j)
					{
						// Needs to check for each delimiter for both the template statement as the variable statement
						$nameOffset = 1 + ($i * $iEnd) + $j;
						
						if (XXX_Type::isValue($matches[$nameOffset][$key]))
						{
							$parsedMatch['name'] = $matches[$nameOffset][$key];
						}
					}
				}
			}
			else
			{
				if (XXX_Type::isValue($matches[1][0]))
				{
					$parsedMatch['name'] = $matches[1][0];
				}
			}
			
			$parsedMatches[] = $parsedMatch;
		}
		
		return $parsedMatches;
	}
	
	public static function getSplitBlocks ($content = '')
	{
		return XXX_String_Pattern::splitToArray($content, self::$patterns['blockSplitter'][0], self::$patterns['blockSplitter'][1]);
	}
	
	public static function getBlockDelimiter ($blockContent = '')
	{
		$parsedMatch = false;
		
		$match = XXX_String_Pattern::getMatch($blockContent, self::$patterns['block'][0], self::$patterns['block'][1]);
				
		// Contains block delimiter
		if ($match)
		{
			$parsedMatch = array
			(
				'fullString' => $match[0],						
				'keyword' => '',
				'name' => '',
				'content' => ''
			);
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					$keywordNamePairOffset = 1 + ($i * 2); // 2 stands for keyword, name pairs
					$last = 1 + ($iEnd * 2); // After all keyword, name pairs the content group remains
					
					if (XXX_Type::isValue($match[$keywordNamePairOffset]))
					{
						$parsedMatch['keyword'] = $match[$keywordNamePairOffset];
						$parsedMatch['name'] = $match[$keywordNamePairOffset + 1];
						$parsedMatch['content'] = $match[$last]; 
						break;
					}
				}
			}
			else
			{
				$parsedMatch['keyword'] = $match[1];
				$parsedMatch['name'] = $match[2];
				$parsedMatch['content'] = $match[3];
			}
			
			if (self::isValidKeyword($parsedMatch['keyword'], 'begin'))
			{
				$parsedMatch['type'] = 'begin';
			}
			else if (self::isValidKeyword($parsedMatch['keyword'], 'end'))
			{
				$parsedMatch['type'] = 'end';
			}
		}
		
		return $parsedMatch;
	}
	
	public static function parseVariables ($blockContent = '')
	{
		$blockVariables = array();
			
		$matches = XXX_String_Pattern::getMatches($blockContent, self::$patterns['variable'][0], self::$patterns['variable'][1]);
		
		foreach ($matches[0] as $key => $value)
		{			
			$blockVariable = array
			(
				'fullString' => $matches[0][$key],
				'name' => ''
			);
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					$k = 1 + $i;
					
					if (XXX_Type::isValue($matches[$k][$key]))
					{
						if (XXX_Type::isValue($matches[$k][$key]))
						{
							$blockVariable['name'] = $matches[$k][$key];
						}
						break;
					}
				}
			}
			else
			{
				if (XXX_Type::isValue($matches[1][$key]))
				{
					$blockVariable['name'] = $matches[1][$key];
				}
			}
			
			$blockVariable['nameParts'] = explode('.', $blockVariable['name']);
			
			if ($blockVariable['nameParts'][0] === self::$placeHolders['block'])
			{
				$blockVariable['type'] = 'subBlock';
				
				unset($blockVariable['nameParts'][0]);
				
				$blockVariable['name'] = XXX_Array::joinValuesToString($blockVariable['nameParts'], '.');
				$blockVariable['nameParts'] = XXX_String::splitToArray($blockVariable['name'], '.'); // TODO ever needed?
			}
			else
			{
				$blockVariable['type'] = 'variable';
			}
			
			$blockVariables[] = $blockVariable;
		}
		
		return $blockVariables;
	}
	
	public static function parseIncludes ($blockContent = '')
	{
		$includes = array();
					
		$matches = XXX_String_Pattern::getMatches($blockContent, self::$patterns['include'][0], self::$patterns['include'][1]);
		
		foreach ($matches[0] as $key => $value)
		{			
			$include = array();
			
			$include['fullString'] = $matches[0][$key];
			$include['include'] = '';
									
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					$k = 1 + ($i * 4); // TODO explain and not 4
					
					if (XXX_Type::isValue($matches[$k][$key]))
					{
						if (XXX_Type::isValue($matches[$k + 1][$key]))
						{
							$include['include'] = $matches[$k + 1][$key];
						}
						else
						{
							$include['include'] = $matches[$k + 2][$key];
						}
						
						break;
					}
				}
			}
			else
			{
				if (XXX_Type::isValue($matches[1 + 1][$key]))
				{
					$include['include'] = $matches[1 + 1][$key];
				}
				else
				{
					$include['include'] = $matches[1 + 2][$key];
				}
			}
			
			$includes[] = $include;
		}
		
		return $includes;
	}
	
	public static function getBlockPlaceHolder ($blockName = '')
	{
		$delimiter = self::getADelimiter();
		$variableKeyword = self::getAVariableKeyword();
			
		return $delimiter[0] . $variableKeyword . self::$placeHolders['block'] . '.' . $blockName . $delimiter[1];
	}
	
		
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Patterns
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public static function constructPatterns ()
	{
		if (XXX_Array::getFirstLevelItemTotal(self::$patterns) == 0)
		{
			$WHITESPACE = '\s*';
			$VARIABLE = '([A-Za-z0-9\._]+)';
			$URI = '([A-Za-z0-9\._/:?=&;]+)';
			$ANYTHING = '(.*?)';
			$ANYTHING_GREEDY = '(.*)';
			$SINGLE_QUOTE = XXX_String_Pattern::escape(XXX_String::$singleQuote);
			$DOUBLE_QUOTES = XXX_String_Pattern::escape(XXX_String::$doubleQuotes);
			$QUOTED_URI = '';					
			$QUOTED_URI .= '(';				
				$QUOTED_URI .= $DOUBLE_QUOTES;
				$QUOTED_URI .= $URI;
				$QUOTED_URI .= $DOUBLE_QUOTES;						
				$QUOTED_URI .= '|';						
				$QUOTED_URI .= $SINGLE_QUOTE;
				$QUOTED_URI .= $URI;
				$QUOTED_URI .= $SINGLE_QUOTE;
			$QUOTED_URI .= ')';	
			
			////////////////////
			// Variable
			////////////////////
			
			$pattern = '';
			
			$pattern .= '(?:';
			
				// Keywords
				$keywords = '';
				
				if (XXX_Type::isArray(self::$keywords['variable']))
				{
					$keywords .= '(?:';
								   
						for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$keywords['variable']); $i < $iEnd; ++$i)
						{
							$keywords .= XXX_String_Pattern::escape(self::$keywords['variable'][$i]);
							
							if ($i < $iEnd - 1)
							{
								$keywords .= '|';
							}
						}
					
					$keywords .= ')';
				}
				else
				{
					$keywords .= XXX_String_Pattern::escape(self::$keywords['variable']);
				}
				
				// Keywords
				
				// Delimiters		
				$delimiters = '';
				
				$delimiters = '(?:';
				
					if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
					{
						for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
						{
							$delimiters .= '(?:';							
								$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
								$delimiters .= $WHITESPACE;					
								$delimiters .= $keywords;
								$delimiters .= $WHITESPACE;
								$delimiters .= $VARIABLE;					
								$delimiters .= $WHITESPACE;
								$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);							
							$delimiters .= ')';
							
							if ($i < $iEnd - 1)
							{
								$delimiters .= '|';
							}
						}
					}
					else
					{
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[0]);
						$delimiters .= $WHITESPACE;				
						$delimiters .= $keywords;
						$delimiters .= $WHITESPACE;
						$delimiters .= $VARIABLE;
						$delimiters .= $WHITESPACE;
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[1]);
					}
				
				$delimiters .= ')';
				// Delimiters
				
				$pattern .= $delimiters;
			
			$pattern .= ')';
			
			self::$patterns['variable'] = $pattern;
			
			////////////////////
			// Block & Block inner
			////////////////////
			
			$pattern = '';		
			$pattern2 = '';
			
			$pattern .= '(?:';
			$pattern2 .= '(?:';
			
			// Keywords
			$keywords = '';
			
			$keywords .= '(';
			
				// Begin
				$keywordsBegin = '';
				
				if (XXX_Type::isArray(self::$keywords['begin']))
				{
					$keywordsBegin .= '(?:';
					
					for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$keywords['begin']); $i < $iEnd; ++$i)
					{
						$keywordsBegin .= self::$keywords['begin'][$i];
						
						if ($i < $iEnd - 1)
						{
							$keywordsBegin .= '|';
						}
					}
					
					$keywordsBegin .= ')';
				}
				else
				{
					$keywordsBegin .= self::$keywords['begin'];
				}				
				// Begin
				
				// End
				$keywordsEnd = '';
				
				if (XXX_Type::isArray(self::$keywords['end']))
				{
					$keywordsEnd .= '(?:';
					
					for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$keywords['end']); $i < $iEnd; ++$i)
					{
						$keywordsEnd .= self::$keywords['end'][$i];
						
						if ($i < $iEnd - 1)
						{
							$keywordsEnd .= '|';
						}
					}
					
					$keywordsEnd .= ')';
				}
				else
				{
					$keywordsEnd .= self::$keywords['end'];
				}				
				// End
				
				$keywords .= $keywordsBegin;
				$keywords .= '|';
				$keywords .= $keywordsEnd;
			
			$keywords .= ')';
			// Keywords
			
			// Delimiters		
			$delimiters = '';
			$delimiters2 = '';
			
			$delimiters = '(?:';
			$delimiters2 = '(?:';
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{		
					$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
					$delimiters .= $WHITESPACE;
					$delimiters .= $keywords;
					$delimiters .= $WHITESPACE;
					$delimiters .= $VARIABLE;
					$delimiters .= $WHITESPACE;
					$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);
					
					$delimiters2 .= $keywords;
					$delimiters2 .= $WHITESPACE;
					$delimiters2 .= $VARIABLE;
					$delimiters2 .= $WHITESPACE;
					$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);
					
					if ($i < $iEnd - 1)
					{
						$delimiters .= '|';
						$delimiters2 .= '|';
					}
				}
			}
			else
			{
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[0]);
				$delimiters .= $WHITESPACE;
				$delimiters .= $keywords;
				$delimiters .= $WHITESPACE;
				$delimiters .= $VARIABLE;
				$delimiters .= $WHITESPACE;
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[1]);
				
				$delimiters2 .= $keywords;
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= $VARIABLE;
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[1]);
			}
			
			$delimiters .= ')';
			$delimiters2 .= ')';
			// Delimiters
			
			$pattern .= $delimiters;
			$pattern2 .= $delimiters2;
					
			$pattern .= ')';
			$pattern .= $ANYTHING_GREEDY;
			
			$pattern2 .= ')';
			$pattern2 .= $ANYTHING_GREEDY;
			
			self::$patterns['block'] = $pattern;
			self::$patterns['blockInner'] = $pattern2;
			
			////////////////////
			// Block splitter
			////////////////////
			
			$pattern = '';
			
			$pattern .= '(?=';
			$pattern .= self::$patterns['block'];
			$pattern .= ')';
			
			self::$patterns['blockSplitter'] = $pattern;
			
			////////////////////
			// Template & Template variable
			////////////////////
			
			$pattern = '';
			$pattern2 = '';
			
			$pattern .= '(?:';
			$pattern2 .= '(?:';
			
			// Keywords
			$keywords = '';
			
			$keywords .= '(?:';
			
			if (XXX_Type::isArray(self::$keywords['template']))
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$keywords['template']); $i < $iEnd; ++$i)
				{
					$keywords .= XXX_String_Pattern::escape(self::$keywords['template'][$i]);
										
					if ($i < $iEnd - 1)
					{
						$keywords .= '|';
					}
				}
			}
			else
			{
				$keywords .= XXX_String_Pattern::escape(self::$keywords['template']);
			}
			
			$keywords .= ')';
			// Keywords
			
			// Delimiters		
			$delimiters = '';
			$delimiters2 = '';
			
			$delimiters = '(?:';
			$delimiters2 = '(?:';
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					$delimiters .= '(?:';					
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
						$delimiters .= $WHITESPACE;					
						$delimiters .= $keywords;
						$delimiters .= $WHITESPACE;					
						$delimiters .= $QUOTED_URI;
						$delimiters .= $WHITESPACE;
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);					
					$delimiters .= ')';
					
					
					
					$delimiters2 .= '(?:';					
						$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
						$delimiters2 .= $WHITESPACE;					
						$delimiters2 .= $keywords;
						$delimiters2 .= $WHITESPACE;					
						$delimiters2 .= self::$patterns['variable'];										
						$delimiters2 .= $WHITESPACE;
						$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);					
					$delimiters2 .= ')';
					
					if ($i < $iEnd - 1)
					{
						$delimiters .= '|';
						$delimiters2 .= '|';
					}
				}
			}
			else
			{
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[0]);
				$delimiters .= $WHITESPACE;				
				$delimiters .= $keywords;
				$delimiters .= $WHITESPACE;					
				$delimiters .= $QUOTED_URI;							
				$delimiters .= $WHITESPACE;
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[1]);
				
				$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[0]);
				$delimiters2 .= $WHITESPACE;				
				$delimiters2 .= $keywords;
				$delimiters2 .= $WHITESPACE;				
				$delimiters2 .= self::$patterns['variable'];								
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[1]);
			}
			
			$delimiters .= ')';
			$delimiters2 .= ')';
			// Delimiters
			
			$pattern .= $delimiters;
			$pattern2 .= $delimiters2;
			
			$pattern .= ')';
			$pattern2 .= ')';
			
			self::$patterns['template'] = $pattern;
			self::$patterns['templateVariable'] = $pattern2;
			
			////////////////////
			// Include & Include variable
			////////////////////
			
			$pattern = '';
			$pattern2 = '';
			
			$pattern .= '(?:';
			$pattern2 .= '(?:';
			
			// Keywords
			$keywords = '';
			
			$keywords .= '(?:';
			
			if (XXX_Type::isArray(self::$keywords['include']))
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$keywords['include']); $i < $iEnd; ++$i)
				{
					$keywords .= XXX_String_Pattern::escape(self::$keywords['include'][$i]);
					
					if ($i < $iEnd - 1)
					{
						$keywords .= '|';
					}
				}
			}
			else
			{
				$keywords .= XXX_String_Pattern::escape(self::$keywords['include']);
			}
			
			$keywords .= ')';
			// Keywords
			
			// Delimiters		
			$delimiters = '';
			$delimiters2 = '';
			
			$delimiters = '(?:';
			$delimiters2 = '(?:';
			
			if (XXX_Type::isArray(self::$delimiters) && XXX_Array::getDeepestLevel(self::$delimiters) > 1)
			{
				for ($i = 0, $iEnd = XXX_Array::getFirstLevelItemTotal(self::$delimiters); $i < $iEnd; ++$i)
				{
					$delimiters .= '(?:';					
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
						$delimiters .= $WHITESPACE;					
						$delimiters .= $keywords;
						$delimiters .= $WHITESPACE;					
						$delimiters .= $QUOTED_URI;									
						$delimiters .= $WHITESPACE;
						$delimiters .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);					
					$delimiters .= ')';
					
					
					$delimiters2 .= '(?:';
						$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[$i][0]);
						$delimiters2 .= $WHITESPACE;					
						$delimiters2 .= $keywords;
						$delimiters2 .= $WHITESPACE;					
						$delimiters2 .= self::$patterns['variable'];										
						$delimiters2 .= $WHITESPACE;
						$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[$i][1]);					
					$delimiters2 .= ')';
					
					if ($i < $iEnd - 1)
					{
						$delimiters .= '|';
						$delimiters2 .= '|';
					}
				}
			}
			else
			{
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[0]);
				$delimiters .= $WHITESPACE;				
				$delimiters .= $keywords;
				$delimiters .= $WHITESPACE;					
				$delimiters .= $QUOTED_URI;							
				$delimiters .= $WHITESPACE;
				$delimiters .= XXX_String_Pattern::escape(self::$delimiters[1]);
				
				
				$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[0]);
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= $keywords;
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= self::$patterns['variable'];
				$delimiters2 .= $WHITESPACE;
				$delimiters2 .= XXX_String_Pattern::escape(self::$delimiters[1]);
			}
			
			$delimiters .= ')';
			$delimiters2 .= ')';
			// Delimiters
			
			$pattern .= $delimiters;
			$pattern2 .= $delimiters2;
			
			$pattern .= ')';
			$pattern2 .= ')';
			
			self::$patterns['include'] = $pattern;
			self::$patterns['includeVariable'] = $pattern2;
			
			////////////////////
						
			foreach (self::$patterns as $key => $value)
			{
				self::$patterns[$key] = array
				(
					0 => $value,
					1 => ''
				);
				
				switch ($key)
				{
					case 'blockInner':
						
						break;
					default:
						self::$patterns[$key][1] = 's';
						break;
				}
			}		
		}
	}
}

?>