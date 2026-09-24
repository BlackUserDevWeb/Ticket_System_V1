/** Types partagés — miroir des Resources Laravel (app/Http/Resources). */
export interface EventSummary {
  id: number; slug: string; title: string; category: string; city: string; venue: string;
  starts_at: string; image_url: string | null; min_price: number | null;
  is_sponsored: boolean; fill_ratio?: number;
}
export interface TicketOffer {
  ticket_type_id: number; name: string; section_id: number; section_name: string;
  price_display: number;         // prix AFFICHÉ = net + frais intégrés (modèle TickPick « no hidden fees »)
  score: number;                 // score qualité/prix /10
  is_best_deal: boolean;
  available: number; seatmap: boolean; dynamic_pricing: boolean;
}
export interface EventDetail extends EventSummary {
  description: string; ends_at: string; lineup: string | null; organizer: { id: number; name: string; logo_url: string | null; color: string | null };
  sections: { id: number; name: string; x: number; y: number; w: number; h: number }[];
  offers: TicketOffer[]; extras: { id: number; name: string; price: number }[];
  has_seatmap: boolean;
}
export interface Order { id: number; reference: string; status: string; total_amount: number; tickets?: { id: number; code: string; qr_url: string | null; seat_label: string | null; transferable: boolean }[] }
export interface Paginated<T> { data: T[]; meta: { current_page: number; last_page: number; total: number } }
