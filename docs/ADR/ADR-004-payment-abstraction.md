# ADR-004 Payment Provider Abstraction

`PaymentGatewayManager` resolves adapters by config `services.payments.default`. Interface in `App\Domain\Payment\Contracts`. SandboxGateway: deterministic fake intents + webhook simulator endpoint (signed) for tests/dev. ManualTransferGateway: admin confirms receipt. Real providers (Midtrans/Xendit/Duitku/Tripay) plug in later without domain changes — STOP condition §1.3 honored (no real merchant data available).
