<?php
class Validate {
	private $errors = array();

	private function translate_flags($string) {
		$output = 0;
		foreach (explode('|', $string) as $filter_str) {
			$filter_str = trim($filter_str);
			switch($filter_str) {
				case 'FILTER_FLAG_STRIP_LOW': $filter = FILTER_FLAG_STRIP_LOW; break;
				case 'FILTER_FLAG_STRIP_HIGH': $filter = FILTER_FLAG_STRIP_HIGH; break;
				case 'FILTER_FLAG_ALLOW_FRACTION': $filter = FILTER_FLAG_ALLOW_FRACTION; break;
				case 'FILTER_FLAG_ALLOW_THOUSAND': $filter = FILTER_FLAG_ALLOW_THOUSAND; break;
				case 'FILTER_FLAG_ALLOW_SCIENTIFIC': $filter = FILTER_FLAG_ALLOW_SCIENTIFIC; break;
				case 'FILTER_FLAG_NO_ENCODE_QUOTES': $filter = FILTER_FLAG_NO_ENCODE_QUOTES; break;
				case 'FILTER_FLAG_ENCODE_LOW': $filter = FILTER_FLAG_ENCODE_LOW; break;
				case 'FILTER_FLAG_ENCODE_HIGH': $filter = FILTER_FLAG_ENCODE_HIGH; break;
				case 'FILTER_FLAG_ENCODE_AMP': $filter = FILTER_FLAG_ENCODE_AMP; break;
				case 'FILTER_NULL_ON_FAILURE': $filter = FILTER_NULL_ON_FAILURE; break;
				case 'FILTER_FLAG_ALLOW_OCTAL': $filter = FILTER_FLAG_ALLOW_OCTAL; break;
				case 'FILTER_FLAG_ALLOW_HEX': $filter = FILTER_FLAG_ALLOW_HEX; break;
				case 'FILTER_FLAG_IPV4': $filter = FILTER_FLAG_IPV4; break;
				case 'FILTER_FLAG_IPV6': $filter = FILTER_FLAG_IPV6; break;
				case 'FILTER_NO_PRIV_RANGE': $filter = FILTER_NO_PRIV_RANGE; break;
				case 'FILTER_NO_RES_RANGE': $filter = FILTER_NO_RES_RANGE; break;
				case 'FILTER_FLAG_PATH_REQUIRED': $filter = FILTER_FLAG_PATH_REQUIRED; break;
				case 'FILTER_FLAG_QUERY_REQUIRED': $filter = FILTER_FLAG_QUERY_REQUIRED; break;
				default: return false;
			}
			$output = $output | $filter;
		}
		return $output;
	}

	public function errors() {
		return $this->errors;
	}

	// Checks multiple values in the request, by using the indicated configuration
	// Validation using PHP filter functions http://www.php.net/manual/en/ref.filter.php
	public function check_multiple (array $params_config, array $request) {
		$unknown_params = $request;
		unset($unknown_params['app']);
		unset($unknown_params['page']);
		foreach ($params_config as $param_name => $param_conf) {
			$value = isset($request[$param_name])? $request[$param_name] : null;
			if (empty($value)) {
				$param_required = isset($param_conf['required'])? $param_conf['required'] : false;
				if ($param_required) {
					$this->errors[] = 'PARAM_REQUIRED_'.strtoupper($param_name);
					log_entry("ERROR: param '$param_name' is required");
				}
			} else {
				$filter_type = isset($param_conf['filter'])? $param_conf['filter'] : null;
				if ($filter_type) {
					log_entry("Checking '$param_name' for filter '$filter_type'"); 
					if ($filter_type == 'callback') {	// Custom callback validator, not PHP's one
						if (!isset($param_conf['filter_options']['callback'])) {
							log_entry ("ERROR: validator function not specified");
							$this->errors[] = 'VALIDATOR_FUNCTION_NOT_FOUND_'.strtoupper($param_name);
						} else {
							// Don't allow methods out of the App's class
							$fn = array($request['app'], $param_conf['filter_options']['callback']);
							$callable_name = '';
							if (!is_callable($fn, $callable_name)) {
								log_entry ("ERROR: cannot find validator function '$callable_name'");
								$this->errors[] = 'VALIDATOR_FUNCTION_NOT_CALLABLE_'.strtoupper($param_name);
							} else {
								$fn_errors = $fn($value, $request);
								if ($fn_errors) {
									if (is_array($fn_errors)) {
										$this->errors = array_merge($this->errors, $fn_errors);
									} else {
										$this->errors[] = $fn_errors;
									}
								}
							}
						}
					} else {
						$options = array();
						if (isset($param_conf['filter_options'])) {
							$options['options'] = $param_conf['filter_options'];
						}
						if (isset($param_conf['filter_flags'])) {
							$flags = $this->translate_flags($param_conf['filter_flags']);
							if ($flags === false) {
								log_entry ("ERROR: Wrong flags: {$param_conf['filter_flags']}");
								$this->errors[] = 'PARAM_WRONG_FLAGS_'.strtoupper($param_name);
							} else {
								$options['flags'] = $flags;
							}
						}

						$filter_id = filter_id($filter_type);
						if ($filter_id === false) {
							log_entry ("ERROR: Filter $filter_type does not exist");
							log_entry ('Available filter types: '.print_r(filter_list(), true));
							$this->errors[] = 'PARAM_WRONG_FILTER_'.strtoupper($filter_type);
						} else {
							if (filter_var($value, $filter_id, $options) === false) {
								log_entry ("ERROR: Filter $filter_type failed for '$param_name'");
								$this->errors[] = 'PARAM_INVALID_'.strtoupper($param_name);
							}
						}
					}
				}
			}
			unset($unknown_params[$param_name]);
		}
		if (count($unknown_params)) {
			log_entry("ERROR: unknown params: ".implode(', ', array_keys($unknown_params)));
			foreach (array_keys($unknown_params) as $up) {
				$this->errors[] = 'PARAM_UNKNOWN_'.strtoupper($up);
			}
		}
		if (count($this->errors)) {
			return false;
		}
		return true;
	}
}
?>
