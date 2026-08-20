"use client";

import { useState } from "react";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/browser";

export function SignOutButton() {
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function signOut() {
    setIsSubmitting(true);
    const supabase = createClient();
    await supabase.auth.signOut();
    window.location.assign(routes.home);
  }

  return (
    <button className="ghost-button" type="button" onClick={signOut} disabled={isSubmitting}>
      {isSubmitting ? "Выходим" : "Выйти"}
    </button>
  );
}
