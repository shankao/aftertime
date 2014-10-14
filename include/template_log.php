<?php
require_once __DIR__.'/template.php';
require_once __DIR__.'/log.php';

// Adds automatic logging
class TemplateLog extends Template {

        static public function render($template_filename, array $template_vars = null, $use_app = false) { 
                log_entry ("TemplateLog::render($template_filename)");
                $result = parent::render($template_filename, $template_vars, $use_app);
                if ($result === false) { 
                        log_entry("ERROR reading $template_filename");
                } 
                return $result;
        }  
}
?>
