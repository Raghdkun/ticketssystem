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
    price: string | null;
    cta: string;
    rtl: boolean;
    qrUrl: string;
    /** A cover is the event page's own hero; nothing is laid over it. */
    bare?: boolean;
};

/** Basalt and paper, the two ends of the brand's own range. */
const DARK = '#12110E';
const LIGHT = '#FAF7F2';
const MUTED_ON_DARK = '#E5DCCC';
const MUTED_ON_LIGHT = '#4A453C';

/**
 * Average brightness of the band the furniture lands on.
 *
 * The prompt asks for a calm lower third but says nothing about whether it
 * comes back light or dark, and it is genuinely both -- a cream screenprint
 * one time, a night scene the next. Reading it is the only way to know which
 * way the text has to go.
 */
function bandLuminance(
    ctx: CanvasRenderingContext2D,
    width: number,
    height: number,
): number {
    const top = Math.round(height * 0.66);
    const { data } = ctx.getImageData(0, top, width, height - top);
    let sum = 0;
    let n = 0;

    // Every fortieth pixel: enough to judge a flat band, cheap enough to run
    // on every redraw.
    for (let i = 0; i < data.length; i += 4 * 40) {
        sum += 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
        n++;
    }

    return n === 0 ? 0 : sum / n;
}

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

    // A cover is the event page's own hero: it already shows the title and a
    // booking button, so laying them on again would only cover the artwork.
    if (layout.bare) {
        return;
    }

    const light = bandLuminance(ctx, width, height) > 140;
    const ink = light ? DARK : LIGHT;
    const muted = light ? MUTED_ON_LIGHT : MUTED_ON_DARK;
    const veil = light ? '250,247,242' : '18,17,14';

    // The prompt asks for a calm lower third, but a model does not always
    // oblige, and the code has to read against whatever turns up.
    const band = height * 0.4;
    const scrim = ctx.createLinearGradient(0, height - band, 0, height);
    scrim.addColorStop(0, `rgba(${veil},0)`);
    scrim.addColorStop(1, `rgba(${veil},0.96)`);
    ctx.fillStyle = scrim;
    ctx.fillRect(0, height - band, width, band);

    const margin = width * 0.06;
    const qrSize = width * 0.22;
    const pad = qrSize * 0.08;
    const plate = qrSize + pad * 2;
    const plateX = rtl ? width - margin - plate : margin;
    const plateY = height - margin - plate;

    // The plate is always the opposite of the band, so the code keeps its
    // quiet zone whichever way the artwork went.
    ctx.fillStyle = light ? DARK : LIGHT;
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
    ctx.fillStyle = ink;

    const lines = wrap(ctx, layout.title, available).slice(0, 3);
    const metaSize = width * 0.03;
    const ctaSize = width * 0.024;
    const blockHeight =
        lines.length * titleSize * 1.2 + metaSize * 1.5 + ctaSize * 1.6;
    let y = plateY + plate / 2 - blockHeight / 2 + titleSize;

    for (const line of lines) {
        ctx.fillText(line, textX, y);
        y += titleSize * 1.2;
    }

    ctx.font = `400 ${metaSize}px "IBM Plex Sans Arabic", "Public Sans", system-ui, sans-serif`;
    ctx.fillStyle = muted;
    ctx.fillText(layout.meta, textX, y + metaSize * 0.2);
    y += metaSize * 1.5;

    // Price, then what to do about it. Somebody reading a poster on a wall
    // needs both, and neither is on the artwork.
    if (layout.price) {
        ctx.font = `700 ${metaSize}px "IBM Plex Sans Arabic", "Public Sans", system-ui, sans-serif`;
        ctx.fillStyle = '#E8A72B';
        ctx.fillText(layout.price, textX, y);
        y += metaSize * 1.4;
    }

    ctx.font = `500 ${ctaSize}px "IBM Plex Sans Arabic", "Public Sans", system-ui, sans-serif`;
    ctx.fillStyle = muted;
    ctx.fillText(layout.cta, textX, y);
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
