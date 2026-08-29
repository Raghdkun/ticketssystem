/**
 * Lays the real title and QR over generated artwork, in the browser.
 *
 * Deliberately not on the server: this project's ImageMagick advertises a
 * Pango delegate that does not work, and every other text path it has renders
 * Arabic as disconnected letterforms. A browser canvas shapes Arabic natively
 * and correctly, needs nothing installed, and lets the owner see the poster
 * before committing to it.
 */
export type PosterLayout = {
    width: number;
    height: number;
    title: string;
    meta: string;
    rtl: boolean;
    qrUrl: string;
};

function load(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error(`could not load ${src}`));
        image.src = src;
    });
}

/** Cover-crop: a squashed subject is worse than one cropped a little tight. */
function cover(
    ctx: CanvasRenderingContext2D,
    image: HTMLImageElement,
    width: number,
    height: number,
) {
    const scale = Math.max(width / image.width, height / image.height);
    const w = image.width * scale;
    const h = image.height * scale;

    ctx.drawImage(image, (width - w) / 2, (height - h) / 2, w, h);
}

export async function drawPoster(
    canvas: HTMLCanvasElement,
    artwork: string,
    layout: PosterLayout,
): Promise<void> {
    const { width, height, rtl } = layout;

    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('canvas 2d context unavailable');
    }

    cover(ctx, await load(artwork), width, height);

    // The prompt asks for a calm lower third, but a model does not always
    // oblige, and the code has to read against whatever turns up.
    const band = height * 0.4;
    const scrim = ctx.createLinearGradient(0, height - band, 0, height);
    scrim.addColorStop(0, 'rgba(18,17,14,0)');
    scrim.addColorStop(1, 'rgba(18,17,14,0.95)');
    ctx.fillStyle = scrim;
    ctx.fillRect(0, height - band, width, band);

    const margin = width * 0.06;
    const qrSize = width * 0.22;
    const pad = qrSize * 0.08;
    const plate = qrSize + pad * 2;
    const plateX = rtl ? width - margin - plate : margin;
    const plateY = height - margin - plate;

    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.roundRect(plateX, plateY, plate, plate, plate * 0.08);
    ctx.fill();

    ctx.drawImage(
        await load(layout.qrUrl),
        plateX + pad,
        plateY + pad,
        qrSize,
        qrSize,
    );

    // Canvas shapes and orders the script itself once told the direction,
    // which is the whole reason this runs here rather than on the server.
    ctx.direction = rtl ? 'rtl' : 'ltr';
    ctx.textAlign = rtl ? 'right' : 'left';
    ctx.textBaseline = 'alphabetic';

    const textX = rtl
        ? width - margin - plate - margin
        : margin + plate + margin;
    const available = width - plate - margin * 3;

    const titleSize = width * 0.055;
    ctx.font = `700 ${titleSize}px "IBM Plex Sans Arabic", "Bricolage Grotesque", system-ui, sans-serif`;
    ctx.fillStyle = '#faf7f2';

    const lines = wrap(ctx, layout.title, available).slice(0, 3);
    let y = plateY + plate / 2 - ((lines.length - 1) * titleSize * 1.2) / 2;

    for (const line of lines) {
        ctx.fillText(line, textX, y);
        y += titleSize * 1.2;
    }

    ctx.font = `400 ${width * 0.03}px "IBM Plex Sans Arabic", "Public Sans", system-ui, sans-serif`;
    ctx.fillStyle = '#e5dccc';
    ctx.fillText(layout.meta, textX, y + width * 0.012);
}

/** Greedy wrap on whitespace; long single words are left to overflow. */
function wrap(
    ctx: CanvasRenderingContext2D,
    text: string,
    max: number,
): string[] {
    const words = text.split(/\s+/).filter(Boolean);
    const lines: string[] = [];
    let line = '';

    for (const word of words) {
        const candidate = line ? `${line} ${word}` : word;

        if (ctx.measureText(candidate).width > max && line) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }

    if (line) {
        lines.push(line);
    }

    return lines;
}
