# ADR-005 Service Fulfillment Engine

Fulfillment behavior is **config-driven**, never category-name-coded. `Service.fulfillment_type` (INSTANT_BOOKING, APPOINTMENT, FIXED_PACKAGE, HOURLY, DAILY, PER_UNIT, SURVEY_REQUIRED, REQUEST_QUOTATION, RFQ, PROJECT, MILESTONE_PROJECT) × `delivery_mode` (REMOTE/ONSITE/HYBRID/PROVIDER_LOCATION) drive: checkout flow, pricing model allowed, dispatch style, order state path, evidence requirements. Category records merely reference policies (review dims, warranty, cancellation, SLA defaults).
