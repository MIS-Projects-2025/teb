// Label + control + validation message.
export default function Field({ label, error, children, htmlFor }) {
    return (
        <div>
            {label && <label className="teb-l" htmlFor={htmlFor}>{label}</label>}
            {children}
            {error && <div className="teb-err">{error}</div>}
        </div>
    );
}
