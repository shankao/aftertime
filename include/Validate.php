<?php
namespace Aftertime;

class Validate {
	private $errors = array();

	private function translateFlags($string) {
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

	// Checks a custom callback validator and adds up the errors found
	public function checkCallback (callable $fn, $value, array $extra_data = null) {
		$fn_errors = call_user_func($fn, $value, $extra_data);
		if ($fn_errors) {
			log_entry ("ERROR: callback validator failed");
			if (is_array($fn_errors)) {
				$this->errors = array_merge($this->errors, $fn_errors);
			} else {
				$this->errors[] = $fn_errors;
			}
		}
	}

	// Validation using PHP filter functions http://www.php.net/manual/en/ref.filter.php
	public function checkFilter ($key, $value, array $spec) {
		$filter_type = $spec['filter'];
		$options = array();
		if (isset($spec['filter_options'])) {
			$options['options'] = $spec['filter_options'];
		}
		if (isset($spec['filter_flags'])) {
			$flags = $this->translateFlags($spec['filter_flags']);
			if ($flags === false) {
				$this->errors[] = 'PARAM_WRONG_FLAGS_'.strtoupper($key);
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
				log_entry ("ERROR: Filter $filter_type failed for '$key'");
				$this->errors[] = 'PARAM_INVALID_'.strtoupper($key);
			}
		}
	}

	public function checkRequired($key, $value, $spec) {
		if (empty($value)) {
			$param_required = isset($spec['required'])? $spec['required'] : false;
			if ($param_required) {
				$this->errors[] = 'PARAM_REQUIRED_'.strtoupper($key);
				log_entry("ERROR: param '$key' is required");
			}
		}
	}

	// Checks one value from the array of all, following the value spec
	private function checkValue ($key, $spec, array $allvars) {
		$value = isset($allvars[$key])? $allvars[$key] : null;
		if (empty($value)) {
			$this->checkRequired($key, $value, $spec);
		} else {
			$filter_type = isset($spec['filter'])? $spec['filter'] : null;
			if ($filter_type) {
				log_entry("Checking '$key' for filter '$filter_type'"); 
				if ($filter_type == 'callback') {	// Custom callback validator, not PHP's one
					if (!isset($spec['filter_options']['callback'])) {
						log_entry ("ERROR: validator function not specified");
						$this->errors[] = 'VALIDATOR_FUNCTION_NOT_FOUND_'.strtoupper($key);
					} else {
						$fn = $spec['filter_options']['callback'];
						if (!is_callable($fn)) {
							log_entry ("ERROR: cannot find validator function '$fn'");
							$this->errors[] = 'VALIDATOR_FUNCTION_NOT_CALLABLE';
						} else {
							$this->checkCallback($fn, $value, $allvars);
						}
					}
				} else {
					$this->checkFilter($key, $value, $spec);
				}
			}
		}
	}

	// Checks multiple values in the request, by using the indicated configuration
	public function checkArray (array $values, array $values_spec) {
		$unknown_values = $values;
		unset($unknown_values['app']);
		unset($unknown_values['page']);
		foreach ($values_spec as $key => $key_spec) {
			$this->checkValue($key, $key_spec, $values);
			unset($unknown_values[$key]);
		}
		if (count($unknown_values)) {
			log_entry("ERROR: unknown params: ".implode(', ', array_keys($unknown_values)));
			foreach (array_keys($unknown_values) as $uk) {
				$this->errors[] = 'PARAM_UNKNOWN_'.strtoupper($uk);
			}
		}
	}

	public function errors() {
		return $this->errors;
	}

	public function hasErrors() {
		return count($this->errors);
	}
}

