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
     * Un pedido se puede cancelar mientras no se haya asignado a un mensajero.
     */
    public function isCancellable(): bool
    {
        return $this === self::PendingInvoice || $this === self::Invoiced;
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
