<?php

class TemplateEngine
{
    private $template_dir;
    private $context;

    public function __construct($template_dir = 'templates')
    {
        $this->template_dir = rtrim($template_dir, '/');
    }

    /**
     * Render a template with the given context data
     * 
     * @param string $template Template filename (without extension)
     * @param array $context Data to pass to template
     * @return string Rendered HTML
     */
    public function render($template, $context = [])
    {
        $this->context = $context;
        
        // Load template file
        $template_path = $this->template_dir . '/' . $template . '.html';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template not found: {$template_path}");
        }
        
        $content = file_get_contents($template_path);
        
        // Process the template
        $content = $this->processIncludes($content);
        $content = $this->processConditionals($content);
        $content = $this->processLoops($content);
        $content = $this->processVariables($content);
        $content = $this->processAudio($content);
        
        return $content;
    }

    /**
     * Process @include directives
     */
    private function processIncludes($content)
    {
        return preg_replace_callback('/@include\s+([^\s\n]+)/', function($matches) {
            $include_path = trim($matches[1]);
            $full_path = $this->template_dir . '/' . $include_path . '.html';
            
            if (!file_exists($full_path)) {
                return "<!-- Include not found: {$include_path} -->";
            }
            
            $included_content = file_get_contents($full_path);
            
            // Recursively process includes in the included file
            $included_content = $this->processIncludes($included_content);
            
            return $included_content;
        }, $content);
    }

    /**
     * Process {{ if condition }} ... {{ else }} ... {{ endif }} blocks
     */
    private function processConditionals($content)
    {
        // Pattern to match if/else/endif blocks (nested support via recursion)
        $pattern = '/\{\{\s*if\s+([^\}]+)\}\}(.*?)(?:\{\{\s*else\s*\}\}(.*?))?\{\{\s*endif\s*\}\}/s';
        
        // Keep processing until no more conditionals found (handles nesting)
        $max_iterations = 10; // Prevent infinite loops
        $iteration = 0;
        
        while (preg_match($pattern, $content) && $iteration < $max_iterations) {
            $content = preg_replace_callback($pattern, function($matches) {
                $condition = trim($matches[1]);
                $if_content = $matches[2];
                $else_content = isset($matches[3]) ? $matches[3] : '';
                
                // Evaluate the condition
                if ($this->evaluateCondition($condition)) {
                    return $if_content;
                } else {
                    return $else_content;
                }
            }, $content);
            $iteration++;
        }
        
        return $content;
    }

    /**
     * Process {{ foreach array as item }} ... {{ endforeach }} blocks
     */
    private function processLoops($content)
    {
        $pattern = '/\{\{\s*foreach\s+(\w+)\s+as\s+(\w+)\s*\}\}(.*?)\{\{\s*endforeach\s*\}\}/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $array_name = trim($matches[1]);
            $item_name = trim($matches[2]);
            $loop_content = $matches[3];
            
            // Get the array from context
            $array = $this->getContextValue($array_name);
            
            if (!is_array($array)) {
                return '<!-- Invalid array: ' . $array_name . ' -->';
            }
            
            $output = '';
            foreach ($array as $item) {
                // Temporarily add loop variable to context
                $original_value = isset($this->context[$item_name]) ? $this->context[$item_name] : null;
                $this->context[$item_name] = $item;
                
                // Process variables in loop content
                $processed = $this->processVariables($loop_content);
                $processed = $this->processConditionals($processed);
                
                $output .= $processed;
                
                // Restore original context
                if ($original_value !== null) {
                    $this->context[$item_name] = $original_value;
                } else {
                    unset($this->context[$item_name]);
                }
            }
            
            return $output;
        }, $content);
    }

    /**
     * Process {{ variable }} replacements
     */
    private function processVariables($content)
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function($matches) {
            $var_name = trim($matches[1]);
            $value = $this->getContextValue($var_name);
            
            // Return empty string if value doesn't exist
            if ($value === null) {
                return '';
            }
            
            return $value;
        }, $content);
    }

    /**
     * Process {{ audio: filename.mp3 }} directives
     */
    private function processAudio($content)
    {
        return preg_replace_callback('/\{\{\s*audio:\s*([^\}]+)\s*\}\}/', function($matches) {
            $filename = trim($matches[1]);
            return '<audio src="/calendar/audio/' . htmlspecialchars($filename) . '" controls></audio>';
        }, $content);
    }

    /**
     * Evaluate a condition string
     */
    private function evaluateCondition($condition)
    {
        // Handle negation
        $negated = false;
        if (strpos($condition, '!') === 0) {
            $negated = true;
            $condition = trim(substr($condition, 1));
        }
        
        // Handle comparison operators
        if (strpos($condition, '==') !== false) {
            list($left, $right) = array_map('trim', explode('==', $condition, 2));
            $result = $this->getContextValue($left) == $this->parseValue($right);
        } elseif (strpos($condition, '!=') !== false) {
            list($left, $right) = array_map('trim', explode('!=', $condition, 2));
            $result = $this->getContextValue($left) != $this->parseValue($right);
        } else {
            // Simple truthiness check
            $value = $this->getContextValue($condition);
            $result = !empty($value);
        }
        
        return $negated ? !$result : $result;
    }

    /**
     * Get a value from context, supporting dot notation (e.g., "day_info.display")
     */
    private function getContextValue($key)
    {
        // Support dot notation for nested arrays
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $value = $this->context;
            
            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            
            return $value;
        }
        
        return isset($this->context[$key]) ? $this->context[$key] : null;
    }

    /**
     * Parse a value that might be a string literal or variable reference
     */
    private function parseValue($value)
    {
        $value = trim($value);
        
        // String literals (quoted)
        if ((strpos($value, '"') === 0 && substr($value, -1) === '"') ||
            (strpos($value, "'") === 0 && substr($value, -1) === "'")) {
            return substr($value, 1, -1);
        }
        
        // Variable reference
        return $this->getContextValue($value);
    }
}