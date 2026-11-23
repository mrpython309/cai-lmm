# CAI LMM integration checklist

CAI LMM serves the customer registration form and stores leads in its own database. Mahindra CRM provides the master data. Configure the calling app with MAHINDRA_API_BASE_URL and MAHINDRA_API_KEY. The CRM reads the matching credential from CAI_LMM_API_KEY. Keep real values in the server environments.

## Lead capture

- Enquiry types and their source campaigns come from the CRM. When a campaign is selected, lead_source_id and lead_source retain its ID and display name. The required local acquisition channel is stored separately in lead_source_channel. A lead without a CRM campaign uses its local channel as the lead_source text.
- Dealer organizations and branches, vehicle models and variants, and lead statuses and responses retain their IDs and display labels. Local recommended models remain available.
- The consultant picker loads all pages of active CRM employees. The lead stores assigned_employee_id and an assigned_employee_name snapshot. Details and copied text include the selected consultant. Older records with only an ID display that ID until a name is captured.
- SaveLeadRequest validates CRM source and model labels as text and validates the channel against the local option catalog. The model and API resource preserve the master references, including the optional organization_id.
- External master IDs do not require local master records. The legacy leads foreign-key migration removes obsolete constraints where present.

## Release verification

1. Check CAI MySQL connectivity and the absence of obsolete local-master foreign keys.
2. Verify the lead-options response, including passenger and cargo choices.
3. Verify the live CRM accepts the configured header and rejects unauthenticated requests.
4. Exercise companies/branches, vehicle-models/variants, enquiry-types/sources, lead-statuses/responses and employees through the CAI proxy. Counts change as administrators maintain master data. An organization may have no branches.
5. Create, read, update, list and delete an isolated test lead. Check the CRM campaign label, the distinct channel and the consultant snapshot through the resource and display labels.
6. Compile Blade views and the route cache. Verify the CRM production asset manifest and an asset over HTTPS. For the Windows Vite regression, run the CRM build from a directory containing an ampersand.
7. Compare each deployed release SHA with its repository's current origin/main. Record actual SHAs in the deployment report.
