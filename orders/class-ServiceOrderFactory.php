<?php
/**
 * Factory to create the appropriate order class
 */
class ServiceOrderFactory
{
    public static function create($order_type, $settings, $day_info, $hymnal, $engine)
    {
        switch ($order_type) {
            case 'matins':
                return new MatinsOrder($settings, $day_info, $hymnal, $engine);
            
            case 'vespers':
                return new VespersOrder($settings, $day_info, $hymnal, $engine);
            
            case 'chief_service':
                return new ChiefServiceOrder($settings, $day_info, $hymnal, $engine);
            
            default:
                throw new \InvalidArgumentException("Unknown order type: $order_type");
        }
    }
}