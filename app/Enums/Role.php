<?php

namespace App\Enums;

/**
 * Roles de usuario del sistema.
 *
 * Reemplaza la tabla `roles` del sistema anterior: los permisos estaban
 * fijos en el código según el id del rol, así que crear roles nuevos desde
 * la interfaz no tenía ningún efecto real.
 */
enum Role: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case CallCenter = 'call_center';
    case Dispatcher = 'dispatcher';
    case Courier = 'courier';
    case SalesRep = 'sales_rep';
    case Billing = 'billing';

    /**
     * Nombre legible del rol para mostrar en interfaz y reportes.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Doctor => 'Médico',
            self::CallCenter => 'Call-Center',
            self::Dispatcher => 'Despachador',
            self::Courier => 'Mensajero',
            self::SalesRep => 'Visitador',
            self::Billing => 'Facturador',
        };
    }
}
