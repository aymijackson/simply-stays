<?php

namespace Modules\Bookings\Support;

/**
 * Field definitions for the Guest Reservation & Registration Form.
 *
 * Mirrors the printed property form section by section, so the online form,
 * the printable view and any hand-filled scans all line up. Fields flagged
 * `staff` are only editable by property staff (hosts/admins); guests see
 * them read-only once staff have filled them in.
 */
class GuestRegistrationForm
{
    public const TITLE = 'Guest Reservation & Registration Form';

    public const INTRO = 'Please complete all applicable fields. One form should be completed for each reservation.';

    public const PAYMENT_STATUSES = ['Paid', 'Part-paid', 'Unpaid'];

    public const PARKING_NOTICE = 'Vehicles parked within the premises are parked at the owner\'s/driver\'s risk. Security personnel monitor access to the premises and carry out security checks; however, their presence does not guarantee against theft, loss or damage. Guests are advised to lock their vehicles, keep valuables out of sight and report any incident immediately to reception/management. This notice does not limit any responsibility that cannot lawfully be excluded.';

    public const ACKNOWLEDGEMENT = 'I confirm that the information provided is accurate, including the number of occupants and any vehicle brought onto the premises. I agree to comply with the apartment\'s house rules, respect other guests and neighbours, observe the stated check-in/check-out times, and follow staff instructions regarding parking and security procedures. I acknowledge the parking notice above where applicable. Any applicable deposits, additional charges and cancellation terms will be communicated separately.';

    public const PRIVACY_NOTE = 'Please provide only the information requested. Guest details should be accessed only by authorised staff and stored securely in line with applicable privacy requirements.';

    /**
     * @return array<string, array{title: string, staff?: bool, notice?: string, fields: array<string, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        return [
            'guest' => [
                'title' => '1. Guest information',
                'fields' => [
                    'full_name' => ['label' => 'Full name (as shown on ID)', 'required' => true],
                    'mobile' => ['label' => 'Mobile number', 'type' => 'tel'],
                    'email' => ['label' => 'Email address', 'type' => 'email'],
                    'address' => ['label' => 'Residential address / City'],
                    'country' => ['label' => 'Country of residence'],
                    'adults' => ['label' => 'Number of adults', 'type' => 'number'],
                    'children' => ['label' => 'Number of children (if any)', 'type' => 'number'],
                    'other_guests' => ['label' => 'Names of other staying guests'],
                ],
            ],
            'reservation' => [
                'title' => '2. Reservation details',
                'fields' => [
                    'reference' => ['label' => 'Reservation / booking reference'],
                    'reserved_on' => ['label' => 'Date reservation was made', 'type' => 'date'],
                    'unit_type' => ['label' => 'Apartment / suite type'],
                    'units' => ['label' => 'Number of units booked', 'type' => 'number'],
                    'check_in' => ['label' => 'Check-in date', 'type' => 'date'],
                    'arrival_time' => ['label' => 'Expected arrival time', 'type' => 'time'],
                    'check_out' => ['label' => 'Check-out date', 'type' => 'date'],
                    'departure_time' => ['label' => 'Expected departure time', 'type' => 'time'],
                    'nights' => ['label' => 'Number of nights', 'type' => 'number'],
                    'channel' => ['label' => 'Booking channel / platform'],
                ],
            ],
            'charges' => [
                'title' => '3. Charges and payment',
                'staff' => true,
                'fields' => [
                    'nightly_rate' => ['label' => 'Nightly rate'],
                    'charge_nights' => ['label' => 'Number of nights', 'type' => 'number'],
                    'additional_charges' => ['label' => 'Additional charges (if any)'],
                    'total_amount' => ['label' => 'Total reservation amount'],
                    'amount_paid' => ['label' => 'Amount paid'],
                    'outstanding' => ['label' => 'Outstanding balance'],
                    'payment_method' => ['label' => 'Payment method'],
                    'payment_status' => ['label' => 'Payment status', 'type' => 'select', 'options' => self::PAYMENT_STATUSES],
                    'payment_reference' => ['label' => 'Payment / transaction reference'],
                    'receipt_by' => ['label' => 'Receipt issued by'],
                ],
            ],
            'requests' => [
                'title' => '4. Guest requests and notes',
                'fields' => [
                    'special_requests' => ['label' => 'Special requests / accessibility needs', 'type' => 'textarea'],
                    'additional_services' => ['label' => 'Additional services requested', 'type' => 'textarea'],
                    'other_notes' => ['label' => 'Other notes', 'type' => 'textarea'],
                    'staff_remarks' => ['label' => 'Staff remarks', 'type' => 'textarea', 'staff' => true],
                ],
            ],
            'emergency' => [
                'title' => '5. Emergency contact (optional)',
                'fields' => [
                    'emergency_name' => ['label' => 'Contact person\'s full name'],
                    'emergency_relationship' => ['label' => 'Relationship to guest'],
                    'emergency_phone' => ['label' => 'Emergency contact phone number', 'type' => 'tel'],
                ],
            ],
            'vehicle' => [
                'title' => '6. Vehicle information',
                'notice' => self::PARKING_NOTICE,
                'fields' => [
                    'vehicle_type' => ['label' => 'Vehicle type'],
                    'vehicle_registration' => ['label' => 'Vehicle registration number'],
                ],
            ],
            'acknowledgement' => [
                'title' => '7. Guest acknowledgement',
                'notice' => self::ACKNOWLEDGEMENT,
                'fields' => [
                    'ack_name' => ['label' => 'Guest\'s full name'],
                    'ack_date' => ['label' => 'Date', 'type' => 'date'],
                    'received_by' => ['label' => 'Received / checked by (staff name)', 'staff' => true],
                ],
            ],
            'office' => [
                'title' => '8. For office use only',
                'staff' => true,
                'fields' => [
                    'id_checked' => ['label' => 'ID checked (type only; do not record ID number here)'],
                    'room_assigned' => ['label' => 'Room / suite assigned'],
                    'keys_by' => ['label' => 'Keys / access provided by'],
                    'checked_in_at' => ['label' => 'Check-in completed at', 'type' => 'datetime-local'],
                    'checked_out_at' => ['label' => 'Check-out completed at', 'type' => 'datetime-local'],
                    'inspection_notes' => ['label' => 'Room inspection / follow-up notes', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    /**
     * Flat map of every field key to its definition, with the section's
     * staff flag pushed down onto each field.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                $field['type'] ??= 'text';
                $field['staff'] = ($section['staff'] ?? false) || ($field['staff'] ?? false);
                $fields[$key] = $field;
            }
        }

        return $fields;
    }

    /**
     * Validation rules for the `data.*` payload. Staff-only fields are only
     * included when the submitter is staff, so guests can never set them.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(bool $asStaff): array
    {
        $rules = [];

        foreach (self::fields() as $key => $field) {
            if ($field['staff'] && ! $asStaff) {
                continue;
            }

            $rule = match ($field['type']) {
                'email' => ['nullable', 'email', 'max:255'],
                'number' => ['nullable', 'integer', 'min:0', 'max:9999'],
                'date' => ['nullable', 'date'],
                'time' => ['nullable', 'date_format:H:i'],
                'datetime-local' => ['nullable', 'date'],
                'textarea' => ['nullable', 'string', 'max:2000'],
                'select' => ['nullable', 'in:'.implode(',', $field['options'])],
                default => ['nullable', 'string', 'max:255'],
            };

            if ($field['required'] ?? false) {
                $rule[0] = 'required';
            }

            $rules["data.$key"] = $rule;
        }

        return $rules;
    }
}
