<?php
declare(strict_types=1);

namespace Ivory\Connection;

/**
 * Manages date styles as configured by the
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-client.html#GUC-DATESTYLE `DateStyle`} configuration
 * setting.
 */
class DateStyle
{
    const FORMAT_ISO = 'ISO';
    const FORMAT_POSTGRES = 'Postgres';
    const FORMAT_SQL = 'SQL';
    const FORMAT_GERMAN = 'German';

    const ORDER_DMY = 'DMY';
    const ORDER_MDY = 'MDY';
    const ORDER_YMD = 'YMD';

    private $format;
    private $order;


    /**
     * @param string $dateStyleStr the date style string, as held in the <tt>DateStyle</tt> configuration setting
     * @return DateStyle
     */
    public static function fromString(string $dateStyleStr): DateStyle
    {
        $parts = preg_split('~\W+~', $dateStyleStr, 2);
        $fmt = $parts[0];
        $ord = ($parts[1] ?? null);

        switch (strtoupper($fmt)) {
            case strtoupper(self::FORMAT_ISO):
                $format = self::FORMAT_ISO;
                $order = self::ORDER_YMD;
                break;

            case strtoupper(self::FORMAT_POSTGRES):
                $format = self::FORMAT_POSTGRES;
                $orders = [self::ORDER_MDY, self::ORDER_DMY];
                break;

            case strtoupper(self::FORMAT_SQL):
                $format = self::FORMAT_SQL;
                $orders = [self::ORDER_MDY, self::ORDER_DMY];
                break;

            case strtoupper(self::FORMAT_GERMAN):
                $format = self::FORMAT_GERMAN;
                $order = self::ORDER_DMY;
                break;

            default:
                trigger_error("Unrecognized DateStyle output format specification: $fmt", E_USER_NOTICE);
                $format = $fmt;
        }

        if (!isset($order)) {
            switch (strtoupper($ord)) {
                case strtoupper(self::ORDER_DMY):
                case strtoupper('Euro'):
                case strtoupper('European'):
                    $order = self::ORDER_DMY;
                    break;

                case strtoupper(self::ORDER_MDY):
                case strtoupper('US'):
                case strtoupper('NonEuro'):
                case strtoupper('NonEuropean'):
                    $order = self::ORDER_MDY;
                    break;

                case strtoupper(self::ORDER_YMD):
                    $order = self::ORDER_YMD;
                    break;

                default:
                    trigger_error("Unrecognized DateStyle input/output year/month/day ordering: $ord", E_USER_NOTICE);
                    $order = $ord;
            }
            if (isset($orders) && !in_array($order, $orders)) {
                $order = $orders[0]; // irrelevant order, set the default one according to $format
            }
        }

        return new DateStyle($format, $order);
    }

    private function __construct(string $format, string $order)
    {
        $this->format = $format;
        $this->order = $order;
    }

    /**
     * @return string the output format specification; one of <tt>DateStyle::FORMAT_*</tt> constants
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return string the input/output specification for year/month/day ordering;
     *                one of <tt>DateStyle::ORDER_*</tt> constants (synonyms are canonicalized to these constants);
     *                irrelevant values are fixed to those valid for the output format
     */
    public function getOrder(): string
    {
        return $this->order;
    }
}
