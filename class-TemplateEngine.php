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
     * Handles nested conditionals properly by processing from innermost to outermost
     */
    private function processConditionals($content)
    {
        $max_iterations = 20; // Prevent infinite loops
        $iteration = 0;
        
        // Keep processing until no more conditionals found (handles nesting)
        while ($iteration < $max_iterations) {
            // Find the innermost conditional (one without nested if/endif inside)
            $processed = $this->processInnermostConditional($content);
            
            // If nothing changed, we're done
            if ($processed === $content) {
                break;
            }
            
            $content = $processed;
            $iteration++;
        }
        
        return $content;
    }
    
    /**
     * Process the innermost conditional block
     */
    private function processInnermostConditional($content)
    {
        // Pattern to match if/else/endif - we'll find them manually to handle nesting
        $if_pattern = '/\{\{\s*if\s+([^\}]+)\}\}/';
        $else_pattern = '/\{\{\s*else\s*\}\}/';
        $endif_pattern = '/\{\{\s*endif\s*\}\}/';
        
        // Find all if positions
        preg_match_all($if_pattern, $content, $if_matches, PREG_OFFSET_CAPTURE);
        
        if (empty($if_matches[0])) {
            return $content; // No conditionals found
        }
        
        // Process from the last if (innermost) backwards
        for ($i = count($if_matches[0]) - 1; $i >= 0; $i--) {
            $if_pos = $if_matches[0][$i][1];
            $if_full = $if_matches[0][$i][0];
            $condition = trim($if_matches[1][$i][0]);
            
            // Find the matching endif for this if
            $endif_pos = $this->findMatchingEndif($content, $if_pos);
            
            if ($endif_pos === false) {
                continue; // No matching endif
            }
            
            // Find else between if and endif (if it exists)
            $else_pos = $this->findElseBetween($content, $if_pos + strlen($if_full), $endif_pos);
            
            // Extract content
            $if_end = $if_pos + strlen($if_full);
            
            if ($else_pos !== false) {
                // Has else block
                $if_content = substr($content, $if_end, $else_pos - $if_end);
                preg_match($else_pattern, substr($content, $else_pos), $else_match);
                $else_full = $else_match[0];
                $else_end = $else_pos + strlen($else_full);
                $else_content = substr($content, $else_end, $endif_pos - $else_end);
            } else {
                // No else block
                $if_content = substr($content, $if_end, $endif_pos - $if_end);
                $else_content = '';
            }
            
            // Check if this block contains nested if/endif
            if ($this->hasNestedConditional($if_content) || $this->hasNestedConditional($else_content)) {
                continue; // Skip this one, process inner ones first
            }
            
            // Evaluate and replace
            $result = $this->evaluateCondition($condition) ? $if_content : $else_content;
            
            // Find the endif tag
            preg_match($endif_pattern, substr($content, $endif_pos), $endif_match);
            $endif_full = $endif_match[0];
            $endif_end = $endif_pos + strlen($endif_full);
            
            // Replace the entire conditional block
            $before = substr($content, 0, $if_pos);
            $after = substr($content, $endif_end);
            
            return $before . $result . $after;
        }
        
        return $content;
    }
    
    /**
     * Find the matching endif for an if at given position
     */
    private function findMatchingEndif($content, $if_pos)
    {
        $depth = 1;
        $pos = $if_pos;
        
        while ($depth > 0 && $pos < strlen($content)) {
            // Find next if or endif
            $next_if = strpos($content, '{{ if', $pos + 1);
            $next_endif = strpos($content, '{{ endif', $pos + 1);
            
            if ($next_endif === false) {
                return false; // No matching endif
            }
            
            if ($next_if !== false && $next_if < $next_endif) {
                // Found nested if before endif
                $depth++;
                $pos = $next_if;
            } else {
                // Found endif
                $depth--;
                $pos = $next_endif;
                
                if ($depth === 0) {
                    return $pos;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Find else between two positions (not inside nested conditionals)
     */
    private function findElseBetween($content, $start, $end)
    {
        $depth = 0;
        $pos = $start;
        
        while ($pos < $end) {
            // Check for if, else, endif
            $next_if = strpos($content, '{{ if', $pos);
            $next_else = strpos($content, '{{ else', $pos);
            $next_endif = strpos($content, '{{ endif', $pos);
            
            // Find the nearest one
            $nearest = false;
            $nearest_type = null;
            
            if ($next_if !== false && $next_if < $end && ($nearest === false || $next_if < $nearest)) {
                $nearest = $next_if;
                $nearest_type = 'if';
            }
            if ($next_else !== false && $next_else < $end && ($nearest === false || $next_else < $nearest)) {
                $nearest = $next_else;
                $nearest_type = 'else';
            }
            if ($next_endif !== false && $next_endif < $end && ($nearest === false || $next_endif < $nearest)) {
                $nearest = $next_endif;
                $nearest_type = 'endif';
            }
            
            if ($nearest === false) {
                break; // No more tags found
            }
            
            if ($nearest_type === 'if') {
                $depth++;
                $pos = $nearest + 1;
            } elseif ($nearest_type === 'endif') {
                $depth--;
                $pos = $nearest + 1;
            } elseif ($nearest_type === 'else' && $depth === 0) {
                return $nearest; // Found else at our level
            } else {
                $pos = $nearest + 1;
            }
        }
        
        return false;
    }
    
    /**
     * Check if content has nested conditionals
     */
    private function hasNestedConditional($content)
    {
        return strpos($content, '{{ if') !== false || strpos($content, '{{if') !== false;
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