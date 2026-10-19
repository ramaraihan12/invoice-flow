<?php

namespace App\Extraction;

use Prism\Prism\Facades\Prism;

class InvoiceExtractor
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You read supplier invoices from Indonesian companies and return their data as JSON.

        Rules:
        - Copy values exactly as printed. Never recompute, round or correct totals, even when the math looks wrong.
        - Amounts are in rupiah and use Indonesian formatting: dots group thousands and a comma starts decimals.
          "Rp 1.250.000" is 1250000 and "1.250.000,00" is 1250000. Return plain numbers.
        - Dates must be YYYY-MM-DD. Month names may be Indonesian (Januari, Februari, Maret, April, Mei, Juni,
          Juli, Agustus, September, Oktober, November, Desember).
        - A line item description is everything between the row number and the quantity. In PDF text the
          quantity is often glued to its unit ("2unit", "3tabung", "1ls"), and that pair is never part of the
          description. Sizes and specs before it are: in "1 Service AC 2 PK 2unit" the description is
          "Service AC 2 PK" and the quantity is 2.
        - The supplier is the seller who issued the invoice, not the customer under "Kepada", "Bill to" or "Pembeli".
        - supplier_npwp is the seller's NPWP with digits only.
        - PPN is VAT. tax_rate is the printed percentage. tax_base is the DPP (Dasar Pengenaan Pajak, sometimes
          "DPP Nilai Lain") only when the invoice prints it, otherwise null.
        - Use null for anything that is not on the document.
        PROMPT;

    // A one or two page invoice plus the schema fits easily. Ollama would
    // otherwise allocate its server default, which can be 32k tokens of VRAM.
    private const OLLAMA_CONTEXT = 8192;

    public function extract(InvoiceDocument $document): ExtractionResult
    {
        $provider = (string) config('invoices.llm.provider');
        $model = (string) config('invoices.llm.model');

        [$prompt, $media] = $document->image !== null
            ? ['Extract the data from this invoice photo.', [$document->image]]
            : ["Extract the data from this invoice text:\n\n".$document->text, []];

        $response = Prism::structured()
            ->using($provider, $model)
            ->withSchema(InvoiceSchema::make())
            ->withSystemPrompt(self::SYSTEM_PROMPT)
            ->withPrompt($prompt, $media)
            // OpenAI's reasoning models reject anything but the default temperature.
            ->usingTemperature($provider === 'openai' ? null : 0)
            ->withMaxTokens(4096)
            ->withClientOptions(['timeout' => (int) config('invoices.llm.timeout')])
            ->withProviderOptions($provider === 'ollama' ? ['num_ctx' => self::OLLAMA_CONTEXT] : [])
            ->asStructured();

        if ($response->structured === []) {
            throw new ExtractionFailed("The model stopped before returning any data ({$response->finishReason->name}).");
        }

        return new ExtractionResult(
            invoice: ExtractedInvoice::fromArray($response->structured),
            raw: $response->structured,
            model: "{$provider}:{$model}",
        );
    }
}
