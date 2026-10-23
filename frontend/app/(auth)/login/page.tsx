import SignInForm from "@/components/auth/SignInForm";
import { Metadata } from "next";

export const metadata: Metadata = {
  title: "Sign in | ERP",
  description: "Sign in to the ERP dashboard.",
};

export default function SignIn() {
  return <SignInForm />;
}
