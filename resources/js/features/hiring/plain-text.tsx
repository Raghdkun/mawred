/**
 * A posting description as written: blank lines separate paragraphs, lines starting with "-" or
 * "•" become bullet lists. Plain text only (no HTML), so nothing from the editor can inject markup.
 */
export function PlainText({ text, className }: { text: string; className?: string }) {
    const blocks = text
        .replace(/\r\n/g, '\n')
        .split(/\n{2,}/)
        .map((block) => block.split('\n').filter((line) => line.trim() !== ''));

    return (
        <div className={className}>
            {blocks.map((lines, i) => {
                const bullets = lines.every((line) => /^\s*[-•]\s+/.test(line));

                return bullets ? (
                    <ul key={i} className="mb-4 list-disc ps-6 last:mb-0">
                        {lines.map((line, j) => (
                            <li key={j}>{line.replace(/^\s*[-•]\s+/, '')}</li>
                        ))}
                    </ul>
                ) : (
                    <p key={i} className="mb-4 whitespace-pre-line last:mb-0">
                        {lines.join('\n')}
                    </p>
                );
            })}
        </div>
    );
}
