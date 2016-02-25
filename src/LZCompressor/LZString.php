<?php
namespace LZCompressor;

class LZString
{
    /**
     * @var string
     */
    private static $keyStr = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    /**
     * @return string
     */
    public static function fromCharCode()
    {
        return array_reduce(func_get_args(), function ($a, $b) {
            $a .= LZString::utf8_chr($b);

            return $a;
        });
    }

    /**
     * Phps chr() equivalent for UTF-8 encoding
     *
     * @param int|string $u
     * @return string
     */
    public static function utf8_chr($u)
    {
        return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * @param string $str
     * @param int $num
     *
     * @return bool|int
     */
    public static function charCodeAt($str, $num)
    {
        return self::utf8_ord(self::utf8_charAt($str, $num));
    }

    /**
     * @param string $ch
     *
     * @return bool|int
     */
    private static function utf8_ord($ch)
    {
        $len = strlen($ch);
        if ($len <= 0) {
            return false;
        }
        $h = ord($ch{0});
        if ($h <= 0x7F) return $h;
        if ($h < 0xC2) return false;
        if ($h <= 0xDF && $len > 1) return ($h & 0x1F) << 6 | (ord($ch{1}) & 0x3F);
        if ($h <= 0xEF && $len > 2) return ($h & 0x0F) << 12 | (ord($ch{1}) & 0x3F) << 6 | (ord($ch{2}) & 0x3F);
        if ($h <= 0xF4 && $len > 3)
            return ($h & 0x0F) << 18 | (ord($ch{1}) & 0x3F) << 12 | (ord($ch{2}) & 0x3F) << 6 | (ord($ch{3}) & 0x3F);

        return false;
    }

    /**
     * @param string $str
     * @param int $num
     *
     * @return string
     */
    private static function utf8_charAt($str, $num)
    {
        return mb_substr($str, $num, 1, 'UTF-8');
    }

    /**
     * @param string $value
     * @param LZData $data
     */
    private static function writeBit($value, LZData $data)
    {
        $data->val = ($data->val << 1) | $value;
        if ($data->position == 15) {
            $data->position = 0;
            $data->str .= self::fromCharCode($data->val);
            $data->val = 0;
        } else {
            $data->position++;
        }
    }

    /**
     * @param string $numbits
     * @param int $value
     * @param LZData $data
     */
    private static function writeBits($numbits, $value, LZData $data)
    {
        if (is_string($value)) $value = self::charCodeAt($value, 0);
        for ($i = 0; $i < $numbits; $i++) {
            self::writeBit($value & 1, $data);
            $value = $value >> 1;
        }
    }

    /**
     * @param LZContext $context
     */
    private static function decrementEnlargeIn(LZContext $context)
    {
        $context->enlargeIn--;
        if ($context->enlargeIn === 0) {
            $context->enlargeIn = pow(2, $context->numBits);
            $context->numBits++;
        }
    }

    /**
     * @param LZContext $context
     *
     * @return LZContext
     */
    private static function produceW(LZContext $context)
    {
        if (array_key_exists($context->w, $context->dictionaryToCreate)) {
            if (self::charCodeAt($context->w, 0) < 256) {
                self::writeBits($context->numBits, 0, $context->data);
                self::writeBits(8, self::utf8_charAt($context->w, 0), $context->data);
            } else {
                self::writeBits($context->numBits, 1, $context->data);
                self::writeBits(16, self::utf8_charAt($context->w, 0), $context->data);
            }
            self::decrementEnlargeIn($context);
            unset($context->dictionaryToCreate[$context->w]);
        } else {
            self::writeBits($context->numBits, $context->dictionary[$context->w], $context->data);
        }
        self::decrementEnlargeIn($context);

        return $context;
    }

    /**
     * @param $input
     *
     * @return string
     */
    public static function compressToBase64($input)
    {
        $output = '';
        $chr1 = 'NaN';
        $chr2 = 'NaN';
        $chr3 = 'NaN';
        $enc1 = 'NaN';
        $enc2 = 'NaN';
        $enc3 = 'NaN';
        $enc4 = 'NaN';
        $input = self::compress($input);
        $i = 0;
        $strlen = mb_strlen($input, 'UTF-8');
        while ($i < ($strlen * 2)) {
            if ($i % 2 === 0) {
                $chr1 = self::charCodeAt($input, $i / 2) >> 8;
                $chr2 = self::charCodeAt($input, $i / 2) & 255;
                if (($i / 2) + 1 < $strlen) {
                    $chr3 = self::charCodeAt($input, ($i / 2) + 1) >> 8;
                } else {
                    $chr3 = 'NaN';
                }
            } else {
                $chr1 = self::charCodeAt($input, ($i - 1) / 2) & 255;
                if (($i + 1) / 2 < $strlen) {
                    $chr2 = self::charCodeAt($input, ($i + 1) / 2) >> 8;
                    $chr3 = self::charCodeAt($input, ($i + 1) / 2) & 255;
                } else {
                    $chr2 = 'NaN';
                    $chr3 = 'NaN';
                }
            }
            $i += 3;

            $enc1 = $chr1 >> 2;
            $enc2 = (($chr1 & 3) << 4) | ($chr2 >> 4);
            $enc3 = (($chr2 & 15) << 2) | ($chr3 >> 6);
            $enc4 = $chr3 & 63;

            if ($chr2 === 'NaN') {
                $enc3 = 64;
                $enc4 = 64;
            } else if ($chr3 === 'NaN') {
                $enc4 = 64;
            }

            $output = $output . self::$keyStr{$enc1} . self::$keyStr{$enc2} . self::$keyStr{$enc3} . self::$keyStr{$enc4};
        }

        return $output;
    }

    /**
     * @param string $uncompressed
     *
     * @return string mixed
     */
    public static function compress($uncompressed)
    {
        $uncompressed = '' . $uncompressed;
        $context = new LZContext();

        for ($i = 0; $i < strlen($uncompressed); $i++) {
            $context->c = self::utf8_charAt($uncompressed, $i);

            if (!array_key_exists($context->c, $context->dictionary)) {
                $context->dictionary[$context->c] = $context->dictSize++;
                $context->dictionaryToCreate[$context->c] = true;
            };

            $context->wc = $context->w . $context->c;
            if (array_key_exists($context->wc, $context->dictionary)) {
                $context->w = $context->wc;
            } else {
                self::produceW($context);
                $context->dictionary[$context->wc] = $context->dictSize++;
                $context->w = $context->c;
            }
        }
        if ($context->w !== '') {
            self::produceW($context);
        }

        self::writeBits($context->numBits, 2, $context->data);

        $safe = 0;
        while (true) {
            $context->data->val = $context->data->val << 1;
            if ($context->data->position == 15) {
                $context->data->str .= self::fromCharCode($context->data->val);
                break;
            }
            $context->data->position++;
        }

        return $context->data->str;
    }

    /**
     * @param LZData $data
     *
     * @return int
     */
    private static function readBit(LZData $data)
    {
        $res = $data->val & $data->position;
        $data->position >>= 1;
        if ($data->position == 0) {
            $data->position = 32768;
            $data->val = self::charCodeAt($data->str, $data->index++);
        }

        return $res > 0 ? 1 : 0;
    }

    /**
     * @param int $numBits
     * @param LZData $data
     *
     * @return int
     */
    private static function readBits($numBits, LZData $data)
    {
        $res = 0;
        $maxpower = pow(2, $numBits);
        $power = 1;
        while ($power != $maxpower) {
            $res |= self::readBit($data) * $power;
            $power <<= 1;
        }

        return $res;
    }

    /**
     * @param string $compressed
     *
     * @return mixed|null|string
     *
     * @throws \Exception
     */
    public static function decompress($compressed)
    {
        $dictionary = [
            0 => 0,
            1 => 1,
            2 => 2
        ];
        $next = null;
        $enlargeIn = 4;
        $dictSize = 4;
        $numBits = 3;
        $entry = '';
        $result = '';
        $w = null;
        $c = null;
        $errorCount = 0;
        $literal = null;
        $data = new LZData;

        $data->str = $compressed;
        $data->val = self::charCodeAt($compressed, 0);
        $data->position = 32768;
        $data->index = 1;

        switch (self::readBits(2, $data)) {
            case 0:
                $c = self::fromCharCode(self::readBits(8, $data));
                break;
            case 1:
                $c = self::fromCharCode(self::readBits(16, $data));
                break;
            case 2:
                return '';
        }
        $dictionary[3] = $c;
        $w = $result = $c;
        while (true) {
            $c = self::readBits($numBits, $data);
            switch ($c) {
                case 0:
                    if ($errorCount++ > 10000) throw new \Exception('Too much errors.');
                    $c = self::fromCharCode(self::readBits(8, $data));
                    $dictionary[$dictSize++] = $c;
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 1:
                    $c = self::fromCharCode(self::readBits(16, $data));
                    $dictionary[$dictSize++] = $c;
                    $c = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 2:
                    return $result;
            }

            if ($enlargeIn === 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }

            if (array_key_exists($c, $dictionary) && $dictionary[$c] !== false) {
                $entry = $dictionary[$c];
            } else {
                if ($c === $dictSize) {
                    $entry = $w . self::utf8_charAt($w, 0);
                } else {
                    throw new \Exception('$c != $dictSize (' . $c . ',' . $dictSize . ')');
                }
            }
            $result .= $entry;
            $dictionary[$dictSize++] = $w . '' . self::utf8_charAt($entry, 0);
            $enlargeIn--;

            $w = $entry;

            if ($enlargeIn == 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }
        }

        return $result;
    }

    /**
     * @param $input
     *
     * @return mixed|null|string
     *
     * @throws \Exception
     */
    public static function decompressFromBase64($input)
    {
        $output = '';
        $ol = 0;
        $output_ = null;
        $chr1 = null;
        $chr2 = null;
        $chr3 = null;
        $enc1 = null;
        $enc2 = null;
        $enc3 = null;
        $enc4 = null;
        $input = preg_replace('/[^A-Za-z0-9\+\/\=]/', '', $input);

        $i = 0;
        while ($i < mb_strlen($input)) {

            $enc1 = strpos(self::$keyStr, $input{$i++});
            $enc2 = strpos(self::$keyStr, $input{$i++});
            $enc3 = strpos(self::$keyStr, $input{$i++});
            $enc4 = strpos(self::$keyStr, $input{$i++});

            $chr1 = ($enc1 << 2) | ($enc2 >> 4);
            $chr2 = (($enc2 & 15) << 4) | ($enc3 >> 2);
            $chr3 = (($enc3 & 3) << 6) | $enc4;

            if ($ol % 2 == 0) {
                $output_ = $chr1 << 8;
                if ($enc3 != 64) {
                    $output .= self::fromCharCode($output_ | $chr2);
                }
                if ($enc4 != 64) {
                    $output_ = $chr3 << 8;
                }
            } else {
                $output = $output . self::fromCharCode($output_ | $chr1);
                if ($enc3 != 64) {
                    $output_ = $chr2 << 8;
                }
                if ($enc4 != 64) {
                    $output .= self::fromCharCode($output_ | $chr3);
                }
            }
            $ol += 3;
        }

        return self::decompress($output);
    }
}