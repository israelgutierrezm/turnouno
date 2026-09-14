# ADR 0009 — Créditos con ledger y enteros escalados

Estado: Aceptado (Slice 5a)

## Contexto

Dinero y créditos **nunca** en float. Los saldos no se almacenan; se derivan de
un ledger auditable (ver CLAUDE.md y MEMBERSHIP_ENGINE.md).

## Decisiones

- **Dinero**: `precio_minor BIGINT` + `moneda CHAR(3)` (89900 MXN = 899.00). Nunca float.
- **Créditos**: enteros escalados. 1 crédito = 1000 unidades (soporta fracciones:
  500 unidades = 0.5 crédito).
- **Derecho (entitlement)**: lo otorga un acuerdo. NO guarda saldo; su saldo es la
  suma de sus movimientos en el ledger (`movimientos_credito.unidades`, con signo:
  + concesión, − consumo).
- **Concesión atómica**: crear acuerdo + derecho + asiento de concesión ocurre dentro
  de una transacción (InnoDB) — consistencia comprador/derecho/ledger.
- Membresías **ilimitadas**: derecho `ilimitado = true`, sin asientos (el saldo no aplica).

## Consecuencias

- El consumo (booking, slice posterior) restará con asientos negativos; el saldo se
  recalcula por suma.
- Ciclos/reset, rollover, holds y add-ons llegan en 5b/5c como asientos/estados adicionales.
- Para rendimiento futuro se podrá cachear el saldo con reconciliación, sin cambiar la
  fuente de verdad (el ledger).
