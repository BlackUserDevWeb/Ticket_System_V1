import { Route, Routes } from "react-router-dom";
import Home from "./pages/Home";
import Search from "./pages/Search";
import EventDetailPage from "./pages/EventDetail";
import Checkout from "./pages/Checkout";
import Auth from "./pages/Auth";
import Account from "./pages/Account";

/**
 * Routage marketplace publique. Les pages sont chargées en code-splitting
 * naturel Vite ; pas de lazy ici car les routes sont peu nombreuses et que
 * le bundle reste petit (perf mobile Togolaise privilégiée).
 */
export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/recherche" element={<Search />} />
      <Route path="/evenement/:slug" element={<EventDetailPage />} />
      <Route path="/achat/:slug" element={<Checkout />} />
      <Route path="/connexion" element={<Auth mode="login" />} />
      <Route path="/inscription" element={<Auth mode="register" />} />
      <Route path="/compte" element={<Account />} />
      <Route path="*" element={<Home />} />
    </Routes>
  );
}
