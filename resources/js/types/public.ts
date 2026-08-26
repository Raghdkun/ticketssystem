export type EventCover = {
    portrait?: string;
    landscape?: string;
    thumb?: string;
    placeholder?: string;
} | null;

export type PublicPlaceLocation = {
    lat: number;
    lng: number;
    address_ar: string | null;
    address_en: string | null;
    landmark_ar: string | null;
    landmark_en: string | null;
};

export type PublicPlace = {
    slug: string;
    name_ar: string;
    name_en: string;
    whatsapp_number: string | null;
    logo: string | null;
    location: PublicPlaceLocation | null;
};

export type PublicEventRule = {
    id: number;
    body_ar: string;
    body_en: string;
};

export type PublicEventPerk = PublicEventRule;

export type GalleryItem = { id: number; path: string };

export type PromoVideo = {
    src: string;
    poster: string | null;
    mime: string;
};

export type PublicEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    description_ar: string | null;
    description_en: string | null;
    price: number;
    currency: string;
    is_free: boolean;
    starts_at: string;
    ends_at: string | null;
    appointments_close_at: string;
    cover: EventCover;
    seats_remaining: number;
    is_open: boolean;
    max_per_appointment: number;
    rules: PublicEventRule[];
    perks: PublicEventPerk[];
    gallery: GalleryItem[];
    promo_video: PromoVideo | null;
};

export type TicketStatus =
    'pending' | 'paid' | 'cancelled' | 'expired' | 'no_show';

export type PublicTicket = {
    token: string;
    full_name: string;
    phone: string;
    quantity: number;
    status: TicketStatus;
    hold_expires_at: string | null;
    verified_at: string | null;
    created_at: string | null;
    qr: string;
};

export type SiblingEvent = {
    slug: string;
    title_ar: string;
    title_en: string;
    starts_at: string;
    cover: string | null;
    is_free: boolean;
    price: number;
    currency: string;
};
