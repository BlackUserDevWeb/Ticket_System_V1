import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "../lib/api";

export interface User { id: number; name: string; email: string; phone: string; role: "buyer" | "organizer" | "admin" | "support"; city_id?: number }
interface AuthCtx { user: User | null; loading: boolean; login: (e: string, p: string) => Promise<void>; register: (d: Record<string, string>) => Promise<void>; logout: () => void; refresh: () => Promise<void> }

const Ctx = createContext<AuthCtx>(null!);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = async () => {
    if (!api.token()) { setUser(null); setLoading(false); return; }
    try { const { data } = await api.get<{ data: User }>("/auth/me"); setUser(data); }
    catch { api.setToken(null); setUser(null); }
    finally { setLoading(false); }
  };
  useEffect(() => { void refresh(); }, []);

  const login = async (email: string, password: string) => {
    const { token, user: u } = await api.post<{ token: string; user: User }>("/auth/login", { email, password });
    api.setToken(token); setUser(u);
  };
  const register = async (d: Record<string, string>) => {
    const { token, user: u } = await api.post<{ token: string; user: User }>("/auth/register", d);
    api.setToken(token); setUser(u);
  };
  const logout = () => { void api.del("/auth/logout").catch(() => {}); api.setToken(null); setUser(null); };

  return <Ctx.Provider value={{ user, loading, login, register, logout, refresh }}>{children}</Ctx.Provider>;
}
