<?php

namespace Andresilva\JsonDatamapping;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class JsonDataMapping
{

    protected $mapping;
    protected $model;

    public static function make(array $mapping, Model $model)
    {
        $jsonMapping = new JsonDataMapping();
        $jsonMapping->mapping = $mapping;
        $jsonMapping->model = $model;

        $obj = [];
        foreach ($mapping as $key => $value) {
            $jsonMapping->checkKeyType($key, $value, $obj, $mapping, $model);
        }

        return $obj;
    }

    private function checkKeyType($key, $value, &$obj, $customDataOption, $model, $globalModel = false)
    {
        $varType = explode(':', $key);

        if (sizeof($varType) === 1) {
            $varType[1] = 'string';
        }

        $valueKey = $customDataOption[$key];

        switch ($varType[1]) {
            case 'int':
                $value = data_get($globalModel ? $this->model : $model, $valueKey, null);

                if (gettype($value) === 'array') {
                    $val = 0;
                    foreach ($value as $key => $v) {
                        $val += $v;
                    }

                    $value = $val;
                }

                $obj[$varType[0]] = (int) $value;
                break;
            case 'float':
                $value = data_get($globalModel ? $this->model : $model, $valueKey, null);

                if (gettype($value) === 'array') {
                    $val = 0.0;
                    foreach ($value as $key => $v) {
                        $val += $v;
                    }

                    $value = $val;
                }

                $obj[$varType[0]] = (float) $value;
                break;
            case 'string':
                $value = data_get($globalModel ? $this->model : $model, $valueKey, null);

                if (gettype($value) === 'array') {
                    $val = '';
                    foreach ($value as $key => $v) {
                        $val .= $v;

                        if (($key + 1) < sizeof($value)) {
                            $separator = '|';

                            if (sizeof($varType) > 2) {
                                $separator = $varType[2];
                            }

                            $val .= $separator;
                        }
                    }

                    $value = $val;
                }

                $obj[$varType[0]] = (string) $value;
                break;
            case 'bool':
                $value = data_get($globalModel ? $this->model : $model, $valueKey, null);

                $obj[$varType[0]] = (bool) $value;
                break;
            case 'object':
                $obj[$varType[0]] = [];

                $datasource = $valueKey['datasource'];
                $mapping = $valueKey['mapping'];

                $data = data_get($globalModel ? $this->model : $model, $datasource, null);

                $o = [];
                foreach ($mapping as $keyMap => $mapItem) {
                    $this->checkKeyType($keyMap, $mapItem, $o, $mapping, $data, false);
                }

                $obj[$varType[0]] = $o;

                break;
            case 'array':
                $obj[$varType[0]] = [];

                $datasource = $valueKey['datasource'];
                $mapping = $valueKey['mapping'];

                $data = data_get($globalModel ? $this->model : $model, $datasource, null) ?? [];

                foreach ($data as $dataItem) {
                    $o = [];

                    $this->iterateArray($dataItem, $mapping, $o);

                    $obj[$varType[0]][] = $o;
                }

                break;
            case 'raw':
                $obj[$varType[0]] = $valueKey;
                break;
            case 'custom':
                $obj[$varType[0]] = [];

                $o = [];
                foreach ($valueKey as $key => $value) {
                    $this->checkKeyType($key, $value, $o, $valueKey, $this->model, true);
                }

                $obj[$varType[0]] = $o;

                break;
            case 'customArray':
                $obj[$varType[0]] = [];

                foreach ($valueKey as $value) {
                    $o = [];
                    foreach ($value as $keyMap => $mapItem) {
                        $this->checkKeyType($keyMap, $mapItem, $o, $value,  $this->model, true);
                    }

                    $obj[$varType[0]][] = $o;
                }

                break;
            case 'math':
                $obj[$varType[0]] = [];
                $math = $varType[2];

                $value = data_get($globalModel ? $this->model : $model, $valueKey, null);

                if (gettype($value) === 'array') {
                    $val = [];
                    foreach ($value as $key => $v) {
                        $val[] = $v;
                    }

                    $value = $val;
                }

                $obj[$varType[0]] = $math($value);

                break;
            case 'expr':
                $obj[$varType[0]] = [];

                // obtem variaveis decladas da expressao
                $vars = $valueKey['vars'];

                $v = [];
                foreach ($vars as $key => $var) {
                    $v[$key] =  data_get($this->model, $var, null);
                }

                $expressionLanguage = new ExpressionLanguage();
                $res = $expressionLanguage->evaluate($valueKey['expr'], $v);

                // verifica se existe extrator
                if (array_key_exists('extract', $valueKey)) {
                    $extractKey = $valueKey['extract'];

                    $ex = null;

                    if (gettype($extractKey) === 'string') {
                        $ex = function ($r) use ($extractKey) {
                            return $r->$extractKey;
                        };
                    }

                    if (gettype($extractKey) === 'array') {
                        $ex = function ($r) use ($extractKey) {
                            $o = [];

                            foreach ($extractKey as $key => $value) {
                                $this->checkKeyType($key, $value, $o, $extractKey, $r, false);
                            }

                            return $o;
                        };
                    }

                    if (gettype($res) === 'array') {
                        $v = [];

                        foreach ($res as $key => $r) {
                            $v[] = $ex($r);
                        }

                        $res = $v;
                    } else {
                        $res = $res->$extractKey;
                    }
                }

                $obj[$varType[0]] = $res;

                break;
            default:
                break;
        }

        return $obj;
    }

    private function iterateArray($dataItem, $mapping, &$o)
    {
        if ($dataItem instanceof \Illuminate\Database\Eloquent\Collection) {
            foreach ($dataItem as $subDataItem) {
                $this->iterateArray($subDataItem, $mapping, $o);
            }
        } else {
            foreach ($mapping as $keyMap => $mapItem) {
                $this->checkKeyType($keyMap, $mapItem, $o, $mapping, $dataItem, false);
            }
        }
    }
}
