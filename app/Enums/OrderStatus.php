<?php

namespace App\Enums;

/**
 * Estado de un pedido desde su creación hasta la entrega.
 */
enum OrderStatus: string
{
    /** Creado por el call-center, esperando factura. */
    case PendingInvoice = 'pending_invoice';

    /** Facturado, listo para que el despachador asigne mensajero. */
    case Invoiced = 'invoiced';

    /** Asignado a un mensajero que aún no sale a ruta. */
    case Assigned = 'assigned';

    /** El mensajero confirmó que va en camino. */
    case InTransit = 'in_transit';

    /** Entregado al cliente. */
    case Delivered = 'delivered';

    /** El mensajero no pudo entregar. */
    case DeliveryFailed = 'delivery_failed';

    /** Anulado antes de la entrega. */
    case Cancelled = 'cancelled';

    /**
     * Un pedido se puede cancelar mientras no se haya asignado a un mensajero,
     * o si la entrega falló (el producto regresa a bodega).
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PendingInvoice, self::Invoiced, self::DeliveryFailed], true);
    }

    /**
     * El despachador puede asignar (o reasignar) mensajero a un pedido facturado,
     * a uno asignado que aún no sale a ruta, o a uno cuya entrega falló.
     */
    public function isAssignable(): bool
    {
        return in_array($this, [self::Invoiced, self::Assigned, self::DeliveryFailed], true);
    }

    /**
     * Nombre legible del estado.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingInvoice => 'Pendiente de factura',
            self::Invoiced => 'Facturado',
            self::Assigned => 'Asignado',
            self::InTransit => 'En ruta',
            self::Delivered => 'Entregado',
            self::DeliveryFailed => 'No entregado',
            self::Cancelled => 'Cancelado',
        };
    }
}
