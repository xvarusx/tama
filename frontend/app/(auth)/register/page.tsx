import SignUpForm from "@/components/auth/SignUpForm";
import { Metadata } from "next";

export const metadata: Metadata = {
  title: "Create account | ERP",
  description: "Create an ERP account.",
};

export default function SignUp() {
  return <SignUpForm />;
}
