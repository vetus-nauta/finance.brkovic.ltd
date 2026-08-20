"use client";

import { useState } from "react";
import { getPublicEnv, hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/browser";

export function AuthForm() {
  const env = getPublicEnv();
  const ready = hasSupabasePublicEnv(env);
  const [email, setEmail] = useState("vetus.nauta@gmail.com");
  const [status, setStatus] = useState<string>("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSubmitting(true);
    setStatus("");

    try {
      const supabase = createClient();
      const { error } = await supabase.auth.signInWithOtp({
        email,
        options: {
          emailRedirectTo: `${window.location.origin}${routes.authCallback}`
        }
      });

      setStatus(error ? error.message : "Код отправлен. Проверьте почту и вернитесь в это окно.");
    } catch (error) {
      setStatus(error instanceof Error ? error.message : "Не удалось отправить код.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit}>
      <label htmlFor="email">Email для входа</label>
      <div className="auth-row">
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          disabled={!ready || isSubmitting}
          required
        />
        <button type="submit" disabled={!ready || isSubmitting}>
          {isSubmitting ? "Отправляем" : "Получить код"}
        </button>
      </div>
      <p className={ready ? "form-note" : "form-note error"}>
        {ready
          ? status || "Вход работает через Supabase Auth. Финансовые данные откроются только после роли в пространстве."
          : "Нужно заполнить NEXT_PUBLIC_SUPABASE_URL и NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY."}
      </p>
    </form>
  );
}
