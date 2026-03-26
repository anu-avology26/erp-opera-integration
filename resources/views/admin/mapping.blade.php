@extends('admin.layout')

@section('title', 'Field Mapping')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Field Mapping</h1>
        <p class="page-subtitle">ERP fields ko yahan map karo; right side me fixed OHIP target dikh raha hai. ERP field names comma-separated dal sakte ho.</p>
    </div>

    <section class="card">
        <h2 class="card-title">ERP Customer Mapping</h2>
        <style>
            .mapping-table th { font-size: 0.7rem; }
            .mapping-label { font-weight: 600; color: var(--text-primary); }
            .mapping-sub { display: block; font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; }
            .mapping-input { min-width: 260px; }
            .mapping-note { color: var(--text-muted); font-size: 0.8rem; }
            .mapping-pill { display: inline-block; padding: 0.2rem 0.45rem; border-radius: 999px; font-size: 0.7rem; background: var(--primary-light); color: var(--primary); font-weight: 600; }
            @media (max-width: 900px) {
                .mapping-table thead { display: none; }
                .mapping-table tr { display: grid; grid-template-columns: 1fr; padding: 0.75rem 0; }
                .mapping-table td { border-bottom: none; padding: 0.4rem 0; }
            }
        </style>
        <form method="post" action="{{ route('admin.mapping.update') }}">
            @csrf
            @php
                $rows = [
                    'erp_number' => [
                        'label' => 'ERP Number',
                        'ohip' => 'Profile keyword (ERPID)',
                        'note' => 'Company profile lookup / unique reference',
                    ],
                    'ar_number' => [
                        'label' => 'AR Number',
                        'ohip' => 'AR accountNo',
                        'note' => 'AR Account post/put',
                    ],
                    'name' => [
                        'label' => 'Name',
                        'ohip' => 'companyName / accountName',
                        'note' => 'Profile + AR account',
                    ],
                    'active' => [
                        'label' => 'Active',
                        'ohip' => 'profileDetails.statusCode',
                        'note' => 'Active/Inactive',
                    ],
                    'blocked' => [
                        'label' => 'Blocked',
                        'ohip' => 'status.restricted',
                        'note' => 'AR Account restriction',
                    ],
                    'account_type' => [
                        'label' => 'Account Type',
                        'ohip' => 'profileDetails.profileType',
                        'note' => 'Company vs other types',
                    ],
                    'address_1' => [
                        'label' => 'Address Line 1',
                        'ohip' => 'addressLine[0]',
                        'note' => 'Profile + AR address',
                    ],
                    'address_2' => [
                        'label' => 'Address Line 2',
                        'ohip' => 'addressLine[1]',
                        'note' => 'Profile + AR address',
                    ],
                    'country' => [
                        'label' => 'Country',
                        'ohip' => 'country.value / country.code',
                        'note' => 'Profile + AR address',
                    ],
                    'post_code' => [
                        'label' => 'Post Code',
                        'ohip' => 'postalCode',
                        'note' => 'Profile + AR address',
                    ],
                    'phone' => [
                        'label' => 'Phone',
                        'ohip' => 'telephone.phoneNumber',
                        'note' => 'Profile',
                    ],
                    'email' => [
                        'label' => 'Email',
                        'ohip' => 'email.emailAddress',
                        'note' => 'Profile + AR email',
                    ],
                    'vat_registration_no' => [
                        'label' => 'VAT Registration No',
                        'ohip' => 'taxInfo.tax1No',
                        'note' => 'Profile',
                    ],
                    'has_credit' => [
                        'label' => 'Has Credit',
                        'ohip' => 'status.restricted (inverse)',
                        'note' => 'hasCredit=true => restricted=false',
                    ],
                    'credit_limit' => [
                        'label' => 'Credit Limit',
                        'ohip' => 'creditLimit.amount',
                        'note' => 'AR Account',
                    ],
                    'payment_terms_code' => [
                        'label' => 'Payment Terms Code',
                        'ohip' => 'Not mapped',
                        'note' => 'Optional / not sent',
                    ],
                    'property' => [
                        'label' => 'Property / Hotel ID',
                        'ohip' => 'hotelId',
                        'note' => 'AR Account',
                    ],
                    'last_modified_at' => [
                        'label' => 'Last Modified At',
                        'ohip' => 'Internal only',
                        'note' => 'Used for incremental sync',
                    ],
                    'restricted_reason' => [
                        'label' => 'Restricted Reason',
                        'ohip' => 'status.restriction',
                        'note' => 'Required when restricted=true',
                    ],
                ];
                $customRows = $customRows ?? [];
            @endphp
            <table class="mapping-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">ERP Fields (editable)</th>
                        <th style="width: 35%;">OHIP Target</th>
                        <th style="width: 25%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $meta)
                        <tr>
                            <td>
                                <div class="mapping-label">{{ $meta['label'] }}</div>
                                <span class="mapping-sub">comma-separated ERP field names</span>
                                <input type="text"
                                       id="{{ $key }}"
                                       name="{{ $key }}"
                                       class="form-input mapping-input"
                                       value="{{ isset($fields[$key]) ? implode(', ', $fields[$key]) : '' }}">
                            </td>
                            <td>
                                <span class="mapping-pill">OHIP</span>
                                <div class="mapping-label">{{ $meta['ohip'] }}</div>
                            </td>
                            <td>
                                <div class="mapping-note">{{ $meta['note'] }}</div>
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($customRows as $row)
                        <tr class="custom-row">
                            <td>
                                <div class="mapping-label">Custom ERP fields</div>
                                <span class="mapping-sub">comma-separated ERP field names</span>
                                <input type="text"
                                       name="custom_erp[]"
                                       class="form-input mapping-input"
                                       value="{{ $row['erp_fields'] ?? '' }}">
                            </td>
                            <td>
                                <span class="mapping-pill">OHIP</span>
                                <input type="text"
                                       name="custom_ohip[]"
                                       class="form-input mapping-input"
                                       value="{{ $row['ohip'] ?? '' }}"
                                       placeholder="e.g. profileDetails.customField">
                            </td>
                            <td>
                                <input type="text"
                                       name="custom_note[]"
                                       class="form-input"
                                       value="{{ $row['note'] ?? '' }}"
                                       placeholder="Optional note">
                                <button type="button" class="btn btn-ghost btn-remove-row" style="margin-top: 0.5rem;">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" id="add-custom-row">Add custom field</button>
                <button type="submit" class="btn">Save Mapping</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var addBtn = document.getElementById('add-custom-row');
    var table = document.querySelector('.mapping-table');
    if (addBtn && table) {
        addBtn.addEventListener('click', function () {
            var tbody = table.querySelector('tbody');
            if (!tbody) return;
            var row = document.createElement('tr');
            row.classList.add('custom-row');
            row.innerHTML =
                '<td><div class="mapping-label">Custom ERP fields</div><span class="mapping-sub">comma-separated ERP field names</span>' +
                '<input type="text" name="custom_erp[]" class="form-input mapping-input" placeholder="e.g. customCode, custom_code"></td>' +
                '<td><span class="mapping-pill">OHIP</span>' +
                '<input type="text" name="custom_ohip[]" class="form-input mapping-input" placeholder="e.g. profileDetails.customField"></td>' +
                '<td><input type="text" name="custom_note[]" class="form-input" placeholder="Optional note">' +
                '<button type="button" class="btn btn-ghost btn-remove-row" style="margin-top: 0.5rem;">Remove</button></td>';
            tbody.appendChild(row);
        });
        table.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('btn-remove-row')) {
                var tr = e.target.closest('tr');
                if (tr) tr.remove();
            }
        });
    }
});
</script>
@endpush
