<?php

namespace Phinder\Parser;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Phinder\Error\{InvalidPattern,InvalidRule,InvalidYaml};
use Phinder\Rule;
use function Funct\Strings\endsWith;


final class RuleParser extends FileParser {

    private $patternParser;

    public function __construct() {
        $this->patternParser = new PatternParser;
    }

    protected function support($path) {
        return endsWith($path, '.yml');
    }

    protected function parseFile($path) {
        $code = $this->getContent($path);
        try {
            $rules = Yaml::parse($code);
        } catch (ParseException $e) {
            throw new InvalidYaml($path);
        }
        if (!\is_array($rules)) {
            throw new InvalidYaml($path);
        }

        for ($i=0; $i<\count($rules); $i++) {
            $arr = $rules[$i];

            try {
                foreach (static::parseArray($arr, $i, $path) as $r) {
                    yield $r;
                }
            } catch (InvalidPattern $e) {
                $e->path = $path;
                throw $e;
            } catch (InvalidRule $e) {
                $e->path = $path;
                $e->index = $i + 1;
                throw $e;
            }
        }
    }

    private function parseArray($arr) {
        $id = $arr['id'];
        $pattern = $arr['pattern'];
        $message = $arr['message'];

        $before = $arr['before'];
        $after = $arr['after'];
        $justification = $arr['justification'];

        if (!\is_string($id)) {
            throw new InvalidRule('id');
        }

        if (!\is_string($message)) {
            throw new InvalidRule('message');
        }

        if (!\is_string($pattern) && !\is_array($pattern)) {
            throw new InvalidRule('pattern');
        }

        if ($justification !== NULL && !\is_string($justification) && !\is_array($justification)) {
            throw new InvalidRule('justification');
        }

        if ($before !== NULL && !\is_string($before) && !\is_array($before)) {
            throw new InvalidRule('before');
        }

        if ($after !== NULL && !\is_string($after) && !\is_array($after)) {
            throw new InvalidRule('after');
        }

        $pats = \is_array($pattern)? $pattern : [$pattern];

        $jsts = NULL;
        if ($justification === NULL) {
            $jsts = [];
        } else {
            if (is_array($justification)) {
                $jsts = $justification;
            } else {
                $jsts = [$justification];
            }
        }

        $bfrs = NULL;
        if ($after === NULL) {
            $bfrs = [];
        } else {
            if (is_array($before)) {
                $bfrs = $before;
            } else {
                $bfrs = [$before];
            }
        }

        $afts = NULL;
        if ($after === NULL) {
            $afts = [];
        } else {
            if (is_array($after)) {
                $afts = $after;
            } else {
                $afts = [$after];
            }
        }

        try {
            foreach ($this->patternParser->parse($pats) as $xpath) {
                yield new Rule($id, $xpath, $message, $jsts, $bfrs, $afts);
            }
        } catch (InvalidPattern $e) {
            $e->id = $id;
            throw $e;
        }
    }

}
