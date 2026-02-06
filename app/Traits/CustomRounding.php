<?php

namespace App\Traits;

trait CustomRounding
{
    /**
     * Aplica regla de redondeo personalizada:
     * - Si decimal <= 0.5: floor() (baja al entero inferior)
     * - Si decimal >= 0.6: ceil() (sube al entero superior)
     * 
     * Ejemplos:
     * - 7.5 -> 7.0
     * - 7.6 -> 8.0
     * - 7.4 -> 7.0
     * - 7.51 -> 8.0
     * 
     * @param float $value
     * @param int $decimals Número de decimales a mantener (default: 2)
     * @return float
     */
    public function customRound(float $value, int $decimals = 2): float
    {
        // Obtener la parte entera y decimal
        $integerPart = floor($value);
        $decimalPart = $value - $integerPart;
        
        // Multiplicar por 10^decimals para trabajar con enteros
        $multiplier = pow(10, $decimals);
        $decimalScaled = $decimalPart * $multiplier;
        
        // Obtener el primer decimal después de los decimales especificados
        $firstDecimal = floor(($decimalScaled - floor($decimalScaled)) * 10);
        
        // Aplicar regla de redondeo
        if ($firstDecimal <= 5) {
            // Redondear hacia abajo
            $roundedDecimal = floor($decimalScaled) / $multiplier;
        } else {
            // Redondear hacia arriba
            $roundedDecimal = ceil($decimalScaled) / $multiplier;
        }
        
        // Si al redondear hacia arriba excedemos 1.0, ajustar
        if ($roundedDecimal >= 1.0) {
            $integerPart += 1;
            $roundedDecimal = 0.0;
        }
        
        return $integerPart + $roundedDecimal;
    }

    /**
     * Helper estático para usar sin instanciar la clase
     * 
     * @param float $value
     * @param int $decimals
     * @return float
     */
    public static function roundCustom(float $value, int $decimals = 2): float
    {
        // Obtener la parte entera y decimal
        $integerPart = floor($value);
        $decimalPart = $value - $integerPart;
        
        // Multiplicar por 10^decimals para trabajar con enteros
        $multiplier = pow(10, $decimals);
        $decimalScaled = $decimalPart * $multiplier;
        
        // Obtener el primer decimal después de los decimales especificados
        $firstDecimal = floor(($decimalScaled - floor($decimalScaled)) * 10);
        
        // Aplicar regla de redondeo
        if ($firstDecimal <= 5) {
            // Redondear hacia abajo
            $roundedDecimal = floor($decimalScaled) / $multiplier;
        } else {
            // Redondear hacia arriba
            $roundedDecimal = ceil($decimalScaled) / $multiplier;
        }
        
        // Si al redondear hacia arriba excedemos 1.0, ajustar
        if ($roundedDecimal >= 1.0) {
            $integerPart += 1;
            $roundedDecimal = 0.0;
        }
        
        return $integerPart + $roundedDecimal;
    }
}

