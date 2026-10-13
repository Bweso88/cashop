import type { Metadata } from "next";
import { RegisterForm } from "@/components/auth/RegisterForm";

export const metadata: Metadata = { title: "Créer un compte" };

export default function RegisterPage() {
  return (
    <>
      <h1 className="text-2xl font-semibold">Créer votre compte</h1>
      <p className="mb-8 mt-1 text-muted">Quelques informations, puis un code par SMS.</p>
      <RegisterForm />
    </>
  );
}
