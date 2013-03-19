<?php

/*

Don't use dots in identifiers

1. Get files, and recursive look for sub templates
2. Split blocks & Replace child blocks with variable placeholders
3. Determine order
4. Determine which blocks are children of which blocks.
5. Set all template and include variables
6. Set variables
7. compose (Process) by replacing all variables (and sub block placeholders with their content)
8. get content


Define options:
- Variables
- Arrays
- Blocks
- Templates [TEMPLATE: "file.html"] gets included and parsed
- Template variables [TEMPLATE: [$VARIABLE]] if then assigned a file value such as file.html it gets included and parsed
- Includes [INCLUDE: "file.ext"] gets included as is
- Include variables 

Caching:
- Run time caching
- Block caching
- Full template caching

Paths:
- cache
- include
- template

// TODO: filter out comments!!!

*/

class XXX_Template
{
	const CLASS_NAME = 'XXX_Template';
		
	protected $paths = array
	(
		'cache' => '',
		'include' => '',
		'template' => ''
	);
		
	protected $defaultContent = array
	(
		'variable' => array('' => ''),
		'block' => array('' => ''),
		'file' => array('' => '')
	);
	
	public static $runTimeCache = array
	(
		'includeVariables' => array(),
		'files' => array()
	);
	
	protected $settings = array
	(
		'ignoreMissingFiles' => true,
		'ignoreMissingBlocks' => true,
		'automaticallyResetSubBlocks' => true,
		'checkForConstants' => true
	);
	
	protected $template = '';
	
	protected $cacheSubPath = 'default';
	
	protected $composedBlocksCached = false;
	protected $templateCached = false;
	
	// Whether or not to cache the structure
	protected $useTemplateCache = true;
	
	
	protected $composedBlocksMaximumLifeTime = 0;
	
	protected $variables = array();
	
	public $blocks = array
	(
		'raw' => array(),
		'composed' => array(),
		'order' => array(),
		'children' => array(),
		'parsedVariables' => array(),
		'parsedIncludes' => array()
	);
	
	protected $fileVariableParents = array();
	
	public function areComposedBlocksCached ()
	{
		return $this->composedBlocksCached;
	}
	
	public function setSetting ($setting, $value)
	{
		$result = false;
		
		if (XXX_Array::hasKey($this->settings, $setting))
		{
			$this->settings[$setting] = $value;
			$result = true;
		}
		
		return $result;
	}
		
	public function setPaths ($paths)
	{
		foreach ($paths as $key => $value)
		{
			$this->setPath($key, $value);
		}
	}
	
	public function setPath ($pathType, $value)
	{
		$result = false;
		
		if (XXX_Array::hasKey($this->paths, $pathType))
		{
			$this->paths[$pathType] = $value;
			$result = true;
		}
		
		return $result;
	}
	
	public function addPath ($pathType, $value)
	{
		$result = false;
		
		if (XXX_Array::hasKey($this->paths, $pathType))
		{
			if (XXX_Type::isArray($this->paths[$pathType]))
			{
				$this->paths[$pathType][] = $value;
			}
			else
			{
				$oldValue = $this->paths[$pathType];
				$this->paths[$pathType] = array();
				$this->paths[$pathType][] = $oldValue;
				$this->paths[$pathType][] = $value;				
			}
			$result = true;
		}
		
		return $result;
	}
		
	public function setAutomaticReset ($value)
	{
		$result = false;
		
		if (XXX_Type::isBoolean($value))
		{
			$result = $this->setSetting('automaticallyResetSubBlocks', $value);
		}
		
		return $result;
	}
	
	public function __construct ($template, $cacheSubPath = 'default', $composedBlocksMaximumLifeTime = 0)
	{
		$this->restart($template, $cacheSubPath, $composedBlocksMaximumLifeTime);
				
		XXX_TemplateParser::constructPatterns();
	}
	
	public function restart ($template, $cacheSubPath = 'default', $composedBlocksMaximumLifeTime = 0)
	{
		$result = true;
		
		if (XXX_Type::isValue($template))
		{
			$this->template = $template;
		}
		else
		{
			$result = false;
			trigger_error('Invalid parameter $template "' . $template . '"', E_USER_WARNING);
		}
		
		if (XXX_Type::isValue($cacheSubPath))
		{
			$this->cacheSubPath = XXX_Path_Local::normalizePath($cacheSubPath, false, true);
		}
		else
		{
			$result = false;
			trigger_error('Invalid parameter $cacheSubPath "' . $cacheSubPath . '"', E_USER_WARNING);
		}
		
		if (XXX_Type::isPositiveInteger($composedBlocksMaximumLifeTime))
		{
			$this->composedBlocksMaximumLifeTime = $composedBlocksMaximumLifeTime;
		}
		else
		{
			$result = false;
			trigger_error('Invalid parameter $composedBlocksMaximumLifeTime "' . $composedBlocksMaximumLifeTime . '"', E_USER_WARNING);
		}
		
		return $result;
	}
	
	public function setup ()
	{
		if ($this->useTemplateCache)
		{
			$this->readTemplateCache();
		}
		
		if ($this->composedBlocksMaximumLifeTime > 0)
		{
			$this->readComposedBlocksCache();
		}
		
		if (!$this->templateCached)
		{			
			$fileContent = $this->recursiveGetTemplateContent($this->template, 'template');
			
			$this->blocks['raw'] = $this->makeTree($fileContent);
			
			$this->fileVariableParents = $this->parseFileVariableParents($this->blocks['raw']);
			
			trigger_error('Template<br>Main Template/File: "' . $this->template . '"<br><br><b>Parsed live</b>');
		}
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Output
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function getContent ($blockName = '')
	{
		$result = '';
		
		$root = false;
		if (XXX_Type::isEmpty($blockName))
		{
			$blockName = $this->blocks['order'][0];
			$root = true;
		}
		
		if ($this->isComposed($blockName))
		{
			$result .= $this->blocks['composed'][$blockName];
		}
		else
		{
			$result = false;
			trigger_error('Block "' . $blockName . '" not composed yet!', E_USER_WARNING);
		}
		
		
		if ($root)
		{
			if (!$this->composedBlocksCached && $this->composedBlocksMaximumLifeTime > 0)
			{
				$this->writeComposedBlocksCache();			
			}
			
			if (!$this->templateCached && $this->useTemplateCache)
			{		
				$this->writeTemplateCache();
			}
		}
		
		return $result;
	}
	
	public function outputContent ($blockName = '')
	{
		echo $this->getContent($blockName);
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Default content
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function setDefaultVariable ($value, $name = '')
	{
		$this->setDefaultContent($value, $name, 'variable');
	}
	
	public function setDefaultBlock ($value, $name = '')
	{
		$this->setDefaultContent($value, $name, 'block');
	}
	
	public function setDefaultFile ($value, $name = '')
	{
		$this->setDefaultContent($value, $name, 'file');
	}
	
	protected function setDefaultContent ($value, $name = '', $type = 'variable')
	{
		if (XXX_Array::hasKey($type, $this->defaultContent) && XXX_Type::isValue($value))
		{
			$this->defaultContent[$type][$name] = $value;
		}
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Parsing
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function compose ($blockName, $composedBlocksMaximumLifeTime = 0)
	{
		$result = true;
		
		if (!$this->composedBlocksCached)
		{
			if ($this->readComposedBlockCache($blockName, $composedBlocksMaximumLifeTime) === false)
			{
				// Actual parsing!!
				$content = '';
				
				if (XXX_Array::hasKey($this->blocks['raw'], $blockName))
				{
					$content = $this->blocks['raw'][$blockName];
				}
				elseif ($this->settings['ignoreMissingBlocks'])
				{
					trigger_error('Missing block: "'.$blockName.'"', E_USER_WARNING);
					
					return; // No need to continue...
				}
				else
				{
					$result = false;
					trigger_error('Missing block: "'.$blockName.'"', E_USER_WARNING);
				}
				
				if (!XXX_Type::isValue($content))
				{
					$result = false;
					trigger_error('Empty/Missing block: "'.$blockName.'"', E_USER_WARNING);
				}
				
				$content = $this->replaceTemplateVariables($content, $blockName);
				
				$content = $this->replaceIncludeVariables($content, $blockName);
				
				$content = $this->replaceIncludes($content, $blockName);
				
				$content = $this->replaceVariables($content, $blockName);
								
				if (XXX_Array::hasKey($this->blocks['composed'], $blockName))
				{
					$this->blocks['composed'][$blockName] .= $content;
				}
				else
				{
					$this->blocks['composed'][$blockName] = $content;
				}
				
				// Reset sub blocks
				if ($this->settings['automaticallyResetSubBlocks'] && XXX_Array::hasKey($this->blocks['children'], $blockName))
				{
					$this->recursiveResetComposedSubBlocks($blockName);
				}
				
				trigger_error('Block<br>Main Template/File: "' . $this->template . '"<br>Block: "' . $blockName . '"<br>cacheSubPath: "' . $this->cacheSubPath . '"<br><b>Composed live</b>');
					
				if ($composedBlocksMaximumLifeTime > 0)
				{
					$this->writeComposedBlockCache($blockName, $composedBlocksMaximumLifeTime);
				}
			}
		}
		
		return $result;
	}
	
	public function replaceTemplateVariables ($content = '', $blockName = '')
	{
		if (XXX_Array::hasKey($this->fileVariableParents['parentBased']['templateVariables'], $blockName))
		{
			foreach ($this->fileVariableParents['parentBased']['templateVariables'][$blockName] as $templateVariable)
			{						
				$value = self::$runTimeCache['templateVariables'][$templateVariable['name']];
				
				if ($value == '')
				{
					if (XXX_Array::hasKey($this->defaultContent['file'], $value))
					{
						$variable = $this->defaultContent['file'][$value];
					}
					else
					{
						$variable = $this->defaultContent['file'][''];
					}
				}
				
				$content = XXX_String::replace($content, $templateVariable['fullString'], $value);
			}
		}
		
		return $content;
	}
	
	public function replaceIncludeVariables ($content = '', $blockName = '')
	{
		if (XXX_Array::hasKey($this->fileVariableParents['parentBased']['includeVariables'], $blockName))
		{
			foreach ($this->fileVariableParents['parentBased']['includeVariables'][$blockName] as $includeVariable)
			{						
				$value = self::$runTimeCache['includeVariables'][$includeVariable['name']];
									
				if ($value == '')
				{
					if (XXX_Array::hasKey($this->defaultContent['file'], $value))
					{
						$variable = $this->defaultContent['file'][$value];
					}
					else
					{
						$variable = $this->defaultContent['file'][''];
					}
				}
				
				$content = XXX_String::replace($content, $includeVariable['fullString'], $value);
			}
		}
		
		return $content;
	}
	
	public function replaceIncludes ($content = '', $blockName = '')
	{
		// If includes already have been parsed use those, saves time...
		if (XXX_Array::hasKey($this->blocks['parsedIncludes'], $blockName))
		{
			$includes = $this->blocks['parsedIncludes'][$blockName];
		}
		else
		{
			$includes = XXX_TemplateParser::parseIncludes($content);
			
			$this->blocks['parsedIncludes'][$blockName] = $includes;
		}
		
		foreach ($includes as $include)
		{
			$value = $this->getFileContent($include['include'], 'include');
			
			if ($value == '')
			{
				if (XXX_Array::hasKey($this->defaultContent['file'], $value))
				{
					$variable = $this->defaultContent['file'][$value];
				}
				else
				{
					$variable = $this->defaultContent['file'][''];
				}
			}
			
			$content = XXX_String::replace($content, $include['fullString'], $value);
		}
		
		return $content;
	}
	
	public function recursiveCompose ($blockName, $composedBlocksMaximumLifeTime = 0)
	{
		if (!$this->composedBlocksCached)
		{
			if (XXX_Array::hasKey($this->blocks['children'], $blockName))
			{
				reset($this->blocks['children'][$blockName]);
				
				foreach ($this->blocks['children'][$blockName] as $subBlock)
				{
					if (XXX_Type::isValue($subBlock))
					{
						$this->recursiveCompose($subBlock, $composedBlocksMaximumLifeTime);
					}
				}
			}
			
			$this->compose($blockName, $composedBlocksMaximumLifeTime);
		}
	}
	
	protected function replaceVariables ($content = '', $blockName = '')
	{
		// Read from parsed cache
		if (XXX_Array::hasKey($this->blocks['parsedVariables'], $blockName))
		{
			$blockVariables = $this->blocks['parsedVariables'][$blockName];
		}
		// Traced live 
		else
		{
			$blockVariables = XXX_TemplateParser::parseVariables($content);
			
			$this->blocks['parsedVariables'][$blockName] = $blockVariables;
		}
		
		if (XXX_Array::getFirstLevelItemTotal($blockVariables) > 0)
		{
			foreach ($blockVariables as $blockVariable)
			{
				$value = '';
								
				// Block
				if ($blockVariable['type'] == 'subBlock')
				{
					// Block exists
					if (XXX_Array::hasKey($this->blocks['composed'], $blockVariable['name']))
					{
						$value = $this->blocks['composed'][$blockVariable['name']];
					}
					else
					{
						if (XXX_Array::hasKey($this->defaultContent['block'], $blockVariable['name']))
						{
							$value = $this->defaultContent['block'][$blockVariable['name']];
						}
						else
						{
							$value = $this->defaultContent['block'][''];
						}
					}
				}
				// Variable
				else
				{
					// Check if the variable exists, also when nested as arrays...
					$value = $this->variables;
					
					foreach ($blockVariable['nameParts'] as $namePart)
					{
						$value = $value[$namePart];
					}
					
					if ($this->settings['checkForConstants'] && XXX_Type::isEmpty($value))
					{
						if (XXX_Type::isConstant($blockVariable['name']))
						{
							$value = constant($blockVariable['name']);
						}
						else
						{
							$value = null;
						}
					}
					
					if (!XXX_Type::isValue($value))
					{
						if (XXX_Array::hasKey($this->defaultContent['variable'], $blockVariable['name']))
						{
							$value = $this->defaultContent['variable'][$blockVariable['name']];
						}
						else
						{
							$value = $this->defaultContent['variable'][''];
						}
					}
					
				}
				
				$content = XXX_String::replace($content, $blockVariable['fullString'], $value);
			}
		}
		
		return $content;
	}
	
	public function isComposed ($blockName)
	{
		return XXX_Array::hasKey($this->blocks['composed'], $blockName);
	}
	
	public function resetComposedBlock ($blockName)
	{
		$this->blocks['composed'][$blockName] = '';
	}
	
	public function recursiveResetComposedSubBlocks ($blockName)
	{
		reset($this->blocks['children'][$blockName]);
		
		foreach ($this->blocks['children'][$blockName] as $subBlockName)
		{
			$this->resetComposedBlock($subBlockName);
		}
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// File
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
		protected function getFileContent ($path = '', $pathType = 'template')
		{
			$fileContent = true;
						
			if (XXX_Type::isEmpty($path))
			{
				$fileContent = false;
				trigger_error('Invalid parameter $path "' . $path . '"', E_USER_WARNING);
			}
			if (!XXX_Array::hasKey($this->paths, $pathType))
			{
				$fileContent = false;
				trigger_error('Invalid parameter $pathType "' . $pathType . '"', E_USER_WARNING);
			}
			
			$path = $this->locateFile($path, $pathType);
			
			if ($fileContent)
			{
				$fileContent = '';
				
				if ($path)
				{		
					// Check if a runTime cached version is available
					if (XXX_Type::isValue(self::$runTimeCache['files'][$pathType][$path]))
					{
						$fileContent = self::$runTimeCache['files'][$pathType][$path];
							
						trigger_error('File: "' . $path . '"<br>Read from the <b>run time cache</b>');
					}
					// Read the file from disk
					else
					{
						$fileContent = XXX_FileSystem_Local::getFileContent($path);
										
						if ($fileContent !== false)
						{
							// Run time cache it
							self::$runTimeCache['files'][$pathType][$path] = $fileContent;
							
							trigger_error('File: "' . $path . '"<br>Read from <b>disk</b>');
						}
						elseif ($this->settings['ignoreMissingFiles'])
						{
							trigger_error('Missing file: "' . $path . '", nowhere to be found or unreadable', E_USER_WARNING);
							
							$fileContent = false;
						}
						else
						{
							$fileContent = false;
							trigger_error('Invalid parameter $path "' . $path . '", nowhere to be found or unreadable', E_USER_WARNING);
						}
					}
				}
			}
			
			return $fileContent;
		}
		
		protected function locateFile ($path = '', $pathType = 'template')
		{		
			if (XXX_Array::hasKey($this->paths, $pathType))
			{
				$found = false;
				
				// Support alternatives of file locations
				if (XXX_Type::isArray($this->paths[$pathType]))
				{
					foreach ($this->paths[$pathType] as $basePath)
					{
						if (XXX_Type::isValue($basePath))
						{
							$tempPath = XXX_Path_Local::extendPath($basePath, $path);
							
							if (XXX_FileSystem_Local::doesFileExist($tempPath))
							{
								$path = $tempPath;
								
								$found = true;
							}
						}
						else
						{
							if (XXX_FileSystem_Local::doesFileExist($path))
							{
								$found = true;
							}
						}
					}
				}
				else
				{
					$basePath = $this->paths[$pathType];
					
					if (XXX_Type::isValue($basePath))
					{
						$tempPath = XXX_Path_Local::extendPath($basePath, $path);
							
						if (XXX_FileSystem_Local::doesFileExist($tempPath))
						{
							$path = $tempPath;
							
							$found = true;
						}
					}
					else
					{
						if (XXX_FileSystem_Local::doesFileExist($path))
						{
							$found = true;
						}
					}
				}
				
				if (!$found)
				{					
					if ($this->settings['ignoreMissingFiles'] && $path !== $this->template)
					{
						trigger_error('Missing file: "' . $path . '", nowhere to be found or unreadable', E_USER_WARNING);			
					}
					else
					{
						trigger_error('Invalid parameter $path "' . $path . '", nowhere to be found or unreadable', E_USER_WARNING);
					}
					
					$path = false;
				}
			}
			
			return $path;
		}
		
		// If there are sub templates
		protected function recursiveGetTemplateContent ($path = '', $pathType ='template')
		{
			$fileContent = $this->getFileContent($path, $pathType);
			
			if ($fileContent)
			{
				$templateFiles = XXX_TemplateParser::parseTemplateFileReferences($fileContent);
								
				foreach ($templateFiles as $templateFile)
				{					
					if ($templateFile['template'] === $this->template)
					{
						trigger_error('Recursion detected! In file: "' . $path . '"', E_USER_ERROR);
					}
					else
					{
						$fileContentFromDisk = $this->recursiveGetTemplateContent($templateFile['template'], 'template');
						
						if ($fileContentFromDisk)
						{
							if (XXX_PHP::$debug)
							{
								$fileContentFromCache = $this->recursiveGetTemplateContent($templateFile['template'], 'template');
								
								$fileContent = XXX_String_Pattern::replace($fileContent, $templateFile['pattern'], '', $fileContentFromDisk, 1);
								$fileContent = XXX_String_Pattern::replace($fileContent, $templateFile['pattern'], '', $fileContentFromCache);
							}
							else
							{
								$fileContent = XXX_String_Pattern::replace($fileContent, $templateFile['pattern'], '', $fileContentFromDisk);
							}
						}
						else
						{
							$fileContent = XXX_String_Pattern::replace($fileContent, $templateFile['pattern'], '', '');
						}
					}
				}
			}
			
			return $fileContent;
		}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Template analyzing
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
		protected function parseFileVariableParents ($blocks)
		{
			$parents = array
			(
				'variableBased' => array
				(
					'includeVariables' => array(),
					'templateVariables' => array()
				),
				'parentBased' => array
				(
					'includeVariables' => array(),
					'templateVariables' => array()
				)
			);
				
			foreach ($blocks as $blockName => $blockContent)
			{
				$parsedIncludeVariables = XXX_TemplateParser::parseFileVariableReference($blockName, $blockContent, 'include');
				
				foreach ($parsedIncludeVariables as $parsedIncludeVariable)
				{
					if (!XXX_Type::isArray($parents['variableBased']['includeVariables'][$parsedIncludeVariable['name']]))
					{
						$parents['variableBased']['includeVariables'][$parsedIncludeVariable['name']] = array();
					}
					
					$parents['variableBased']['includeVariables'][$parsedIncludeVariable['name']][] = $parsedIncludeVariable;
					
					
					if (!XXX_Type::isArray($parents['parentBased']['includeVariables'][$parsedIncludeVariable['blockName']]))
					{
						$parents['parentBased']['includeVariables'][$parsedIncludeVariable['blockName']] = array();
					}
					
					$parents['parentBased']['includeVariables'][$parsedIncludeVariable['blockName']][] = $parsedIncludeVariable;
				}
				
				$parsedTemplateVariables = XXX_TemplateParser::parseFileVariableReference($blockName, $blockContent, 'template');
				
				foreach ($parsedTemplateVariables as $parsedTemplateVariable)
				{
					
					if (!XXX_Type::isArray($parents['variableBased']['templateVariables'][$parsedTemplateVariable['name']]))
					{
						$parents['variableBased']['templateVariables'][$parsedTemplateVariable['name']] = array();
					}
					
					$parents['variableBased']['templateVariables'][$parsedTemplateVariable['name']][] = $parsedTemplateVariable;
					
					
					if (!XXX_Type::isArray($parents['parentBased']['templateVariables'][$parsedTemplateVariable['blockName']]))
					{
						$parents['parentBased']['templateVariables'][$parsedTemplateVariable['blockName']] = array();
					}
					
					$parents['parentBased']['templateVariables'][$parsedTemplateVariable['blockName']][] = $parsedTemplateVariable;
				}
			}
			
			return $parents;
		}
		
		// Separates all the blocks, replace child blocks with placeholder variables, determines their order (for recursive parsing), and which blocks are children of which blocks
		protected function makeTree ($content, $parentBlockName = '')
		{
			$blocks = array();
					
			// Split the content into blocks
			$contentBlocks = XXX_TemplateParser::getSplitBlocks($content);
			
			if (XXX_Type::isValue($parentBlockName))
			{
				$blockNameParts = XXX_String::splitToArray($parentBlockName, '.');
				$depth = XXX_Array::getFirstLevelItemTotal($blockNameParts);
			}
			else
			{
				$blockNameParts = array();
				$depth = 0;
			}
			
			foreach ($contentBlocks as $key => $value)
			{
				$parsedMatch = XXX_TemplateParser::getBlockDelimiter($value);
				
				// Contains block delimiter
				if ($parsedMatch)
				{					
					// Begin of a block
					if ($parsedMatch['type'] == 'begin')
					{	
						$parentBlockName = XXX_Array::joinValuesToString($blockNameParts, '.');
						
						// Add one level
						$blockNameParts[$depth++] = $parsedMatch['name'];
						
						$blockName = XXX_Array::joinValuesToString($blockNameParts, '.');
						
						// Build block parsing order (reverse)
						$this->blocks['order'][] = $blockName;
						
						// Add contents
						if (XXX_Type::isValue($blocks[$blockName]))
						{
							$blocks[$blockName] .= $parsedMatch['content'];
						}
						else
						{
							$blocks[$blockName] = $parsedMatch['content'];
						}
						
						// Replace sub block part within the parent block with [$__BLOCK__.blockName] variable
						$blocks[$parentBlockName] .= XXX_TemplateParser::getBlockPlaceHolder($blockName);
						
						// Store the parent-child relation between this block and its parent.
						$this->blocks['children'][$parentBlockName][] = $blockName;
						
						// Store sub block names for automatic resetting and recursive parsing when there is no child block!
						//$this->blocks['children'][$blockName][] = ''; // TODO is this used???
					}
					// End of a block
					else if ($parsedMatch['type'] == 'end')
					{
						unset($blockNameParts[--$depth]);
						
						$parentBlockName = XXX_Array::joinValuesToString($blockNameParts, '.');
						
						// Add rest of block to the parent block
						$blocks[$parentBlockName] .= $parsedMatch['content'];
					}
				}
				// No block delimiter
				else
				{	
					$blockName = XXX_Array::joinValuesToString($blockNameParts, '.');
									
					// Add contents
					if (XXX_Array::hasKey($blocks, $blockName))
					{
						$blocks[$blockName] .= $value;
					}
					else
					{
						$blocks[$blockName] = $value;
					}
				}
			}
			
			return $blocks;
		}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Setting
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
		public function setVariable ($name, $value = '', $reset = true)
		{
			if (!$this->composedBlocksCached)
			{
				// Arrays, multiple assignments at once
				if (XXX_Type::isArray($name))
				{
					foreach ($name as $k => $v)
					{
						$this->setVariableSub($k, $v, $reset);
					}
				}
				// Single assignment
				else
				{
					$this->setVariableSub($name, $value, $reset);
				}
			}
		}
		
		public function setVariables (array $variables, $reset = true)
		{
			$this->setVariable($variables, '', $reset);
		}
		
		protected function setVariableSub ($name, $value = '', $reset = true)
		{
			if (XXX_Type::isArray($value))
			{
				// Remove old array children
				if ($reset)
				{
					$this->variables[$name] = array();
				}
				
				foreach ($value as $k => $v)
				{
					$this->variables[$name][$k] = $v;
				}
			}
			else
			{
				$this->variables[$name] = $value;
			}
		}
		
		public function setIncludeVariable ($name, $value = '')
		{
			if (!$this->composedBlocksCached)
			{
				// Arrays, multiple assignments at once
				if (XXX_Type::isArray($name))
				{
					foreach ($name as $k => $v)
					{
						$this->setIncludeVariableSub($k, $v);
					}
				}
				// Variables
				else
				{
					$this->setIncludeVariableSub($name, $value);
				}
			}
		}
		
		public function setIncludeVariables (array $includeVariables)
		{
			$this->setIncludeVariable($includeVariables, '');
		}
			
		protected function setIncludeVariableSub ($name, $value = '')
		{
			// Only accept file variables that are actually in the template		
			if (XXX_Array::hasKey($this->fileVariableParents['variableBased']['includeVariables'], $name) && XXX_Type::isValue($value))
			{
				// TODO is double... only store a reference to the runTimeCache['files'] record
				
				self::$runTimeCache['includeVariables'][$name] = $this->getFileContent($value, 'include');
			}
		}
		
		public function setTemplateVariable ($name, $value = '')
		{
			if (!$this->composedBlocksCached)
			{
				// Arrays, multiple assignments at once
				if (XXX_Type::isArray($name))
				{
					foreach ($name as $k => $v)
					{
						$this->setTemplateVariableSub($k, $v);
					}
				}
				// Variables
				else
				{
					$this->setTemplateVariableSub($name, $value);
				}
			}
		}
		
		public function setTemplateVariables (array $templateVariables)
		{
			$this->setTemplateVariable($templateVariables, '');
		}
		
		protected function setTemplateVariableSub ($name, $value = '')
		{
			if ($value === $this->template)
			{
				if (XXX_PHP::$debug)
				{
					$this->debugMessages[] = 'Recursion detected! In file: "' . $value . '"';
				}
			}
			else
			{
				// Only accept file variables that are actually in the template
				if (XXX_Array::hasKey($this->fileVariableParents['variableBased']['templateVariables'], $name) && XXX_Type::isValue($value))
				{
					$value = $this->recursiveGetTemplateContent($value, 'template');
					
					foreach ($this->fileVariableParents['variableBased']['templateVariables'][$name] as $parent)
					{
						if (XXX_Array::hasKey($this->blocks['raw'], $parent['blockName']))
						{
							$parent['content'] = $this->blocks['raw'][$parent['blockName']];
							
							$parent['content'] = str_replace($parent['fullString'], $value, $parent['content']);
							
							unset($this->fileVariableParents['variableBased']['templateVariables'][$name]);
							
							foreach ($this->fileVariableParents['parentBased']['templateVariables'][$parent['blockName']] as $key => $value)
							{
								if ($value['name'] == $name)
								{
									unset($this->fileVariableParents['parentBased']['templateVariables'][$parent['blockName']][$key]);
								}
							}
							
							$newUnparsedBlocks = $this->makeTree($parent['content'], $parent['blockName']);
							$this->blocks['raw'] = XXX_Array::merge($this->blocks['raw'], $newUnparsedBlocks);
							
							$newParents = $this->parseFileVariableParents($this->blocks['raw']);					
							$this->fileVariableParents = XXX_Array::merge($this->fileVariableParents, $newParents);
						}
					}
				}
			}
		}
		
	////////////////////////////////////////////////////////////////////////////////////////////////////
	// Caching
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
		////////////////////
		// Template
		////////////////////
		
			protected function getTemplateCacheAbsoluteFile ()
			{
				return XXX_Path_Local::extendPath($this->paths['cache'], $this->cacheSubPath . XXX_OperatingSystem::$directorySeparator . XXX_TemplateParser::$placeHolders['template'] . '.tmp');
			}
			
			protected function getTemplateAbsoluteFile ()
			{
				return XXX_Path_Local::extendPath($this->paths['template'], $this->template);
			}
			
			protected function readTemplateCache ()
			{
				$templateCacheAbsoluteFile = $this->getTemplateCacheAbsoluteFile();
				$templateAbsoluteFile = $this->getTemplateAbsoluteFile();
				
				if ($this->useTemplateCache)			
				{
					if (XXX_FileSystem_Local::doesFileExist($templateCacheAbsoluteFile) && XXX_FileSystem_Local::doesFileExist($templateAbsoluteFile))
					{
						$templateModified = XXX_FileSystem_Local::getFileModifiedTimestamp($templateAbsoluteFile);
						$templateCacheModified = XXX_FileSystem_Local::getFileModifiedTimestamp($templateCacheAbsoluteFile);
						
						if ($templateCacheModified >= $templateModified)
						{
							$data = XXX_FileSystem_Local::getFileContent($templateCacheAbsoluteFile);
							
							if ($data)
							{
								$data = XXX_String_PHPON::decode($data);
								
								$this->runTimeCache = $data['runTimeCache'];
								$this->defaultContent = $data['defaultContent'];
								$this->blocks = $data['blocks'];
								
								trigger_error('Template cache<br>Main Template/File: "' . $this->template . '"<br>File: "' . $templateCacheAbsoluteFile . '"<br>cacheSubPath: "' . $this->cacheSubPath . '"<br><b>Read</b> from <b>disk cache</b>');
								
								$this->templateCached = true;
							}
							elseif ($this->settings['ignoreMissingFiles'])
							{
								trigger_error('Missing file: "' . $templateCacheAbsoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);
							}
							else
							{
								trigger_error('Invalid variable $templateCacheAbsoluteFile "' . $templateCacheAbsoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);
							}
						}
						else
						{
							// Expired file
							trigger_error('Template cache<br>Cache file: "' . $templateCacheAbsoluteFile . '"<br>Now <b>expired</b>');
							
							$this->deleteTemplateCache();
						}
					}
				}
			}
			
			protected function writeTemplateCache ()
			{
				if ($this->useTemplateCache)
				{
					$absoluteFile = $this->getTemplateCacheAbsoluteFile();
					
					$data = array
					(
						'runTimeCache' => $this->runTimeCache,
						'defaultContent' => $this->defaultContent,
						'blocks' => $this->blocks
					);
					
					$data['blocks']['composed'] = array();
												
					$temp = XXX_FileSystem_Local::writeFileContent($absoluteFile, XXX_String_PHPON::encode($data));
					
					if (!$temp)
					{
						trigger_error('Template cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>written</b> to <b>disk cache</b>');
					}
				}
			}
			
			protected function deleteTemplateCache ()
			{
				$absoluteFile = $this->getTemplateCacheAbsoluteFile();
				
				if (XXX_FileSystem_Local::doesFileExist($absoluteFile))
				{
					trigger_error('Template cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>deleted</b> from <b>disk cache</b>');
					
					XXX_FileSystem_Local::deleteFile($absoluteFile);
				}
			}
		
		////////////////////
		// Blocks
		////////////////////
		
			protected function getComposedBlocksCacheAbsoluteFile ()
			{
				return XXX_Path_Local::extendPath($this->paths['cache'], $this->cacheSubPath . XXX_OperatingSystem::$directorySeparator . XXX_TemplateParser::$placeHolders['block'] . '.tmp');
			}
			
			protected function readComposedBlocksCache ()
			{
				$absoluteFile = $this->getComposedBlocksCacheAbsoluteFile();
				
				if ($this->composedBlocksMaximumLifeTime > 0)			
				{
					if (XXX_FileSystem_Local::doesFileExist($absoluteFile))
					{
						$composedBlocksCacheModified = XXX_FileSystem_Local::getFileModifiedTimestamp($absoluteFile);					
						$composedBlocksCacheMaximumModified = XXX_TimestampHelpers::getCurrentTimestamp() - $this->composedBlocksMaximumLifeTime;
						
						$composedBlocksCacheRemainingLifeTime = ($composedBlocksCacheModified - $composedBlocksCacheMaximumModified);
						
						if ($composedBlocksCacheModified >= $composedBlocksCacheMaximumModified)
						{
							$data = XXX_FileSystem_Local::getFileContent($absoluteFile);
							
							if ($data)
							{
								$data = XXX_String_PHPON::decode($data);
								
								$this->blocks['composed'] = $data['composedBlocks'];
								
								trigger_error('Composed blocks cache<br>Main Template/File: "' . $this->template . '"<br>File: "' . $absoluteFile . '"<br>cacheSubPath: "' . $this->cacheSubPath . '"<br><b>Read</b> from <b>disk cache</b>, expires in <b>' . $composedBlocksCacheRemainingLifeTime . '</b> seconds');
								
								$this->composedBlocksCached = true;
							}
							elseif ($this->settings['ignoreMissingFiles'])
							{
								trigger_error('Missing file: "' . $absoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);			
							}
							else
							{
								trigger_error('Invalid variable $absoluteFile "' . $absoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);
							}
						}
						else
						{
							// Expired file
							trigger_error('Composed blocks cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>expired</b>');
							
							$this->deleteComposedBlocksCache();
						}
					}
				}
			}
			
			protected function writeComposedBlocksCache ()
			{
				if ($this->composedBlocksMaximumLifeTime > 0)
				{
					$absoluteFile = $this->getComposedBlocksCacheAbsoluteFile();
					
					$data = array
					(
						'composedBlocks' => $this->blocks['composed']
					);
								
					$temp = XXX_FileSystem_Local::writeFileContent($absoluteFile, XXX_String_PHPON::encode($data));
					
					if (!$temp)
					{
						trigger_error('Composed blocks cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>written</b> to <b>disk cache</b>');
					}
				}
			}
			
			protected function deleteComposedBlocksCache ()
			{
				$absoluteFile = $this->getComposedBlocksCacheAbsoluteFile();
				
				if (XXX_FileSystem_Local::doesFileExist($absoluteFile))
				{
					trigger_error('Composed blocks cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>deleted</b> from <b>disk cache</b>');
					
					XXX_FileSystem_Local::deleteFile($absoluteFile);
				}
			}
		
		////////////////////
		// Block
		////////////////////
		
			protected function getComposedBlockCacheAbsoluteFile ($blockName)
			{
				return XXX_Path_Local::extendPath($this->paths['cache'], $this->cacheSubPath . XXX_OperatingSystem::$directorySeparator . XXX_TemplateParser::$placeHolders['block'] . $blockName . '.tmp');
			}
			
			protected function readComposedBlockCache ($blockName, $composedBlocksMaximumLifeTime = 0)
			{
				$result = false;
				
				$absoluteFile = $this->getComposedBlockCacheAbsoluteFile($blockName);
				
				if ($composedBlocksMaximumLifeTime > 0 && XXX_FileSystem_Local::doesFileExist($absoluteFile))
				{
					$absoluteFileModified = XXX_FileSystem_Local::getFileModifiedTimestamp($absoluteFile);
					$maximumModified = time() - $composedBlocksMaximumLifeTime;
					
					$expires = ($absoluteFileModified - $maximumModified);
					
					if ($absoluteFileModified >= $maximumModified)
					{
						if ($blockContent = XXX_FileSystem_Local::getFileContent($absoluteFile))
						{
							$blockContent = XXX_String_PHPON::decode($blockContent);
							
							trigger_error('Composed block cache<br>Main Template/File: "' . $this->template . '"<br>Block "' . $blockName . '"<br>cacheSubPath: "' . $this->cacheSubPath . '"<br><b>Read</b> from <b>disk cache</b>, expires in <b>' . $expires . '</b> seconds');
							
							$this->blocks['composed'][$blockName] = $blockContent;
							$result = true;
						}
						elseif ($this->settings['ignoreMissingFiles'])
						{
							trigger_error('Missing file: "' . $absoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);
						}
						else
						{
							$result = false;
							trigger_error('Invalid parameter $blockName "' . $blockName . '" - "' . $absoluteFile . '", nowhere to be found or unreadable', E_USER_WARNING);
						}
					}
					else
					{
						// Expired file
						trigger_error('Composed block cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>expired</b>');
						
						$this->deleteComposedBlockCache($blockName);
					}
				}
				
				return $result;
			}
			
			protected function writeComposedBlockCache ($blockName, $composedBlocksMaximumLifeTime = 0)
			{
				if ($composedBlocksMaximumLifeTime > 0)
				{
					$absoluteFile = $this->getComposedBlockCacheAbsoluteFile($blockName);
											
					$temp = XXX_FileSystem_Local::writeFileContent($absoluteFile, XXX_String_PHPON::encode($this->blocks['composed'][$blockName]));
					
					if (!$temp)
					{
						trigger_error('Composed block cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>written</b> to <b>disk cache</b>');
					}
				}
			}
			
			protected function deleteComposedBlockCache ($blockName)
			{
				$absoluteFile = $this->getComposedBlockCacheAbsoluteFile($blockName);
				
				if (XXX_FileSystem_Local::doesFileExist($absoluteFile))
				{
					trigger_error('Composed block cache<br>Cache file: "' . $absoluteFile . '"<br>Now <b>deleted</b> from <b>disk cache</b>');
					
					XXX_FileSystem_Local::deleteFile($absoluteFile);
				}
			}
}

?>