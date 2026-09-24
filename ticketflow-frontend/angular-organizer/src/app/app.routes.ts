import { Routes } from "@angular/router";

/**
 * Routage du back-office organisateur — lazy loading par feature
 * (bundle initial minimal, chargement a la demande des ecrans peu utilises).
 */
export const routes: Routes = [
  { path: "login", loadComponent: () => import("./features/login.component").then((m) => m.LoginComponent) },
  {
    path: "",
    loadComponent: () => import("./shell.component").then((m) => m.ShellComponent),
    children: [
      { path: "", loadComponent: () => import("./features/dashboard/dashboard.component").then((m) => m.DashboardComponent) },
      { path: "evenements", loadComponent: () => import("./features/events/events-list.component").then((m) => m.EventsListComponent) },
      { path: "evenements/:id/billetterie", loadComponent: () => import("./features/tickets/ticket-types.component").then((m) => m.TicketTypesComponent) },
      { path: "evenements/:id/pricing", loadComponent: () => import("./features/pricing/dynamic-pricing.component").then((m) => m.DynamicPricingComponent) },
      { path: "scanner", loadComponent: () => import("./features/scanner/scanner.component").then((m) => m.ScannerComponent) },
      { path: "checkin", loadComponent: () => import("./features/checkin/checkin.component").then((m) => m.CheckinComponent) },
      { path: "fonds", loadComponent: () => import("./features/funds/funds.component").then((m) => m.FundsComponent) },
      { path: "messages", loadComponent: () => import("./features/messaging/messages.component").then((m) => m.MessagesComponent) },
      { path: "**", redirectTo: "" },
    ],
  },
];
