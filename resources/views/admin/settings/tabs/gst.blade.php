<div class="row g-3">
    <x-admin.input name="gst_legal_name" label="Legal name" :value="settings('gst_legal_name')"
                   help="Printed on GST invoices from Phase 5." />
    <x-admin.input name="gst_number" label="GSTIN" :value="settings('gst_number')"
                   help="15 characters, for example 07AABCF1234M1Z5." />
    <x-admin.input name="gst_pan" label="PAN" :value="settings('gst_pan')" help="Format AAAAA9999A." />
    <x-admin.input name="gst_cin" label="CIN (optional)" :value="settings('gst_cin')" />
    <x-admin.input name="gst_state" label="State" :value="settings('gst_state')" />
    <x-admin.input name="gst_state_code" label="State code" :value="settings('gst_state_code')"
                   help="Two digits — decides CGST/SGST vs IGST." />
    <x-admin.input name="gst_default_rate" label="Default GST rate (%)" type="number" step="0.01"
                   :value="settings('gst_default_rate')" required
                   help="Per-product rates are set in Phase 2." />
</div>
