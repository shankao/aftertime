<?php
require_once 'include/template.php';
require_once 'include/log.php';

// Adds automatic logging
class TemplateLog extends Template {

        static public function render($template_filename, array $vars = null) { 
                log_entry ("TemplateLog::render($template_filename)");
                $result = parent::render($template_filename, $vars);
                if ($result === false) { 
                        log_entry("ERROR reading $template_filename");
                } 
                return $result;
        }  
}
?>
