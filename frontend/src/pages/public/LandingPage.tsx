import { motion } from 'framer-motion';
import { Factory, MapPin, Award, Settings, Users, Phone, Mail, ArrowRight, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ColorModeButton } from '@/components/ui/ColorModeToggle';

const FADE_UP = {
  hidden: { opacity: 0, y: 30 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.6 } }
};

const STAGGER_CONTAINER = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: {
      staggerChildren: 0.2
    }
  }
};

export default function LandingPage() {
  const [formStatus, setFormStatus] = useState<'idle' | 'submitting' | 'success'>('idle');

  const handleContactSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setFormStatus('submitting');
    setTimeout(() => setFormStatus('success'), 1500);
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-primary-950 text-primary-900 dark:text-neutral-100 font-sans">
      {/* Navigation */}
      <nav className="fixed w-full z-50 bg-white/80 dark:bg-primary-900/80 backdrop-blur-md border-b border-gray-200 dark:border-primary-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-20">
            <div className="flex items-center space-x-3">
              <img src="/build/logo.svg" alt="Ogami ERP" className="h-8 w-auto dark:brightness-0 dark:invert dark:opacity-90" />
            </div>
            <div className="hidden md:flex items-center space-x-10 text-sm font-semibold tracking-wide uppercase text-primary-500">
              <a href="#about" className="hover:text-primary-900 dark:text-neutral-100 transition-colors">About</a>
              <a href="#capabilities" className="hover:text-primary-900 dark:text-neutral-100 transition-colors">Capabilities</a>
              <a href="#history" className="hover:text-primary-900 dark:text-neutral-100 transition-colors">Legacy</a>
              <Link to="/recruit" className="hover:text-primary-900 dark:text-neutral-100 transition-colors">Recruit</Link>
              <a href="#contact" className="hover:text-primary-900 dark:text-neutral-100 transition-colors">Contact</a>
            </div>
            <div className="flex items-center space-x-5">
              <Link to="/login" className="text-sm font-bold text-primary-700 dark:text-primary-300 hover:text-accent dark:hover:text-accent transition-colors mr-2">
                Portal Login →
              </Link>
              <ColorModeButton />
            </div>
          </div>
        </div>
      </nav>

      <section className="relative pt-32 pb-24 lg:pt-56 lg:pb-40 overflow-hidden bg-primary-50 dark:bg-primary-900 text-primary-900 dark:text-neutral-100 flex justify-center items-center rounded-b-[3rem] border-b border-primary-200 dark:border-primary-800 shadow-sm">
        {/* Subtle geometric pattern rather than full opacity overlay */}
        <div className="absolute inset-0 opacity-[0.03] bg-[radial-gradient(#000_1px,transparent_1px)] [background-size:16px_16px]"></div>
        
        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mt-12">
          <motion.div initial="hidden" animate="visible" variants={STAGGER_CONTAINER} className="max-w-4xl mx-auto">
            <motion.span variants={FADE_UP} className="inline-block py-1.5 px-4 rounded-full bg-white dark:bg-primary-900 text-primary-600 dark:text-neutral-400 text-xs font-bold tracking-[0.2em] mb-8 border border-primary-200 dark:border-primary-800 shadow-sm uppercase">
              Founded 1996 • Manila, Philippines
            </motion.span>
            <motion.h1 variants={FADE_UP} className="text-5xl md:text-7xl font-sans font-black tracking-tight mb-8 leading-[1.1] text-primary-900 dark:text-neutral-100">
              Modernizing <br className="hidden md:block"/>
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-accent to-blue-400">Industrial Manufacturing</span>
            </motion.h1>
            <motion.p variants={FADE_UP} className="text-lg md:text-xl text-primary-500 dark:text-neutral-400 mb-12 max-w-2xl mx-auto leading-relaxed font-medium">
              We deliver precision plastic injection molding and tool making at scale. Operating out of the FCIE Industrial Estate.
            </motion.p>
            <motion.div variants={FADE_UP} className="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
              <a href="#capabilities" className="w-full sm:w-auto inline-flex justify-center items-center px-10 py-4 border border-transparent dark:border-white text-sm font-bold uppercase tracking-wider rounded-xl text-white bg-primary-900 hover:bg-black shadow-lg shadow-primary-900/20 transition-all hover:-translate-y-0.5">
                Explore Capabilities
              </a>
              <Link to="/recruit" className="w-full sm:w-auto inline-flex justify-center items-center px-10 py-4 border-2 border-blue-200 text-sm font-bold uppercase tracking-wider rounded-xl text-blue-900 bg-blue-50 hover:border-blue-300 hover:bg-blue-100 shadow-subtle transition-all hover:-translate-y-0.5">
                Apply For Jobs
              </Link>
              <a href="#contact" className="w-full sm:w-auto inline-flex justify-center items-center px-10 py-4 border-2 border-primary-200 dark:border-primary-800 text-sm font-bold uppercase tracking-wider rounded-xl text-primary-900 dark:text-neutral-100 bg-white dark:bg-primary-900 hover:border-primary-300 hover:bg-primary-50 dark:bg-primary-900 shadow-subtle transition-all hover:-translate-y-0.5">
                Get in Touch
              </a>
            </motion.div>
          </motion.div>
        </div>
      </section>

      {/* Quick Stats Strip */}
      <section className="bg-white dark:bg-primary-900 py-16 -mt-8 relative z-10 max-w-5xl mx-auto rounded-3xl shadow-xl shadow-primary-900/5 mb-16 border border-primary-100 dark:border-primary-800">
        <div className="px-6 lg:px-12">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-12 divide-x divide-primary-100">
            <div className="text-center px-4">
              <div className="text-4xl font-black text-primary-900 dark:text-neutral-100 tracking-tighter mb-2">28+</div>
              <div className="text-xs font-bold text-primary-400 uppercase tracking-widest">Years Active</div>
            </div>
            <div className="text-center px-4">
              <div className="text-4xl font-black text-accent tracking-tighter mb-2">400</div>
              <div className="text-xs font-bold text-primary-400 uppercase tracking-widest">Employees</div>
            </div>
            <div className="text-center px-4">
              <div className="text-4xl font-black text-primary-900 dark:text-neutral-100 tracking-tighter mb-2">2</div>
              <div className="text-xs font-bold text-primary-400 uppercase tracking-widest">ISO Certs</div>
            </div>
            <div className="text-center px-4">
              <div className="text-4xl font-black text-primary-900 dark:text-neutral-100 tracking-tighter mb-2">HP</div>
              <div className="text-xs font-bold text-primary-400 uppercase tracking-widest">Precision</div>
            </div>
          </div>
        </div>
      </section>

      {/* Capabilities Section */}
      <section id="capabilities" className="py-24 bg-gray-50 dark:bg-primary-950">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center max-w-3xl mx-auto mb-16">
            <h2 className="text-3xl font-extrabold text-primary-900 dark:text-neutral-100 sm:text-4xl">Core Capabilities</h2>
            <p className="mt-4 text-lg text-primary-600">
              Trusted by leading automotive and air pump manufacturers for high-quality, high-volume production.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            <motion.div initial="hidden" whileInView="visible" viewport={{ once: true }} variants={FADE_UP} className="bg-white dark:bg-primary-900 rounded-2xl p-8 border border-gray-100 dark:border-primary-800 shadow-sm hover:shadow-md transition-shadow">
              <div className="w-12 h-12 bg-accent-soft text-accent rounded-xl flex items-center justify-center mb-6">
                <Settings className="w-6 h-6" />
              </div>
              <h3 className="text-xl font-bold text-primary-900 dark:text-neutral-100 mb-3">Plastic Injection Molding</h3>
              <p className="text-primary-600 dark:text-neutral-400 leading-relaxed">
                Specialized in thermoplastic and thermosetting molding operations using advanced machinery to deliver precise components at scale.
              </p>
            </motion.div>

            <motion.div initial="hidden" whileInView="visible" viewport={{ once: true }} variants={FADE_UP} className="bg-white dark:bg-primary-900 rounded-2xl p-8 border border-gray-100 dark:border-primary-800 shadow-sm hover:shadow-md transition-shadow">
              <div className="w-12 h-12 bg-accent-soft text-accent rounded-xl flex items-center justify-center mb-6">
                <Factory className="w-6 h-6" />
              </div>
              <h3 className="text-xl font-bold text-primary-900 dark:text-neutral-100 mb-3">Mold Manufacturing</h3>
              <p className="text-primary-600 dark:text-neutral-400 leading-relaxed">
                Dedicated mold factory established in 2017, providing in-house design, fabrication, and maintenance of high-precision molds.
              </p>
            </motion.div>

            <motion.div initial="hidden" whileInView="visible" viewport={{ once: true }} variants={FADE_UP} className="bg-white dark:bg-primary-900 rounded-2xl p-8 border border-gray-100 dark:border-primary-800 shadow-sm hover:shadow-md transition-shadow">
              <div className="w-12 h-12 bg-accent-soft text-accent rounded-xl flex items-center justify-center mb-6">
                <Award className="w-6 h-6" />
              </div>
              <h3 className="text-xl font-bold text-primary-900 dark:text-neutral-100 mb-3">Certified Quality</h3>
              <p className="text-primary-600 dark:text-neutral-400 leading-relaxed">
                Rigorous quality assurance backed by DIN EN ISO 9001:2000 and ISO 14001 certifications to meet global standards.
              </p>
            </motion.div>
          </div>
        </div>
      </section>

      {/* History Timeline */}
      <section id="history" className="py-24 bg-white dark:bg-primary-900 border-t border-gray-200 dark:border-primary-800">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="mb-16">
            <h2 className="text-3xl font-extrabold text-primary-900 dark:text-neutral-100 sm:text-4xl text-center">Our Journey</h2>
          </div>
          
          <div className="space-y-8 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-200 before:to-transparent">
            {/* Timeline Item */}
            <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
              <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-accent text-white shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 font-mono text-xs font-bold ring-4 ring-accent-soft">
                1996
              </div>
              <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl bg-gray-50 dark:bg-primary-950 border border-gray-100 dark:border-primary-800 shadow-sm">
                <h4 className="font-bold text-primary-900 dark:text-neutral-100 text-lg mb-1">Company Establishment</h4>
                <p className="text-primary-600 dark:text-neutral-400 text-sm">Founded under PEZA as a special export enterprise inside Cavite Rosario Industrial Estate.</p>
              </div>
            </div>
            
            <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
              <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-primary-200 text-primary-600 dark:text-neutral-400 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 font-mono text-xs font-bold">
                2002
              </div>
              <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl bg-gray-50 dark:bg-primary-950 border border-gray-100 dark:border-primary-800 shadow-sm">
                <h4 className="font-bold text-primary-900 dark:text-neutral-100 text-lg mb-1">Independent FCIE Factory</h4>
                <p className="text-primary-600 dark:text-neutral-400 text-sm">Built and relocated to a standalone facility at the FCIE Industrial Estate in Dasmariñas, Cavite.</p>
              </div>
            </div>

            <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
              <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-primary-200 text-primary-600 dark:text-neutral-400 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 font-mono text-xs font-bold">
                2007
              </div>
              <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl bg-gray-50 dark:bg-primary-950 border border-gray-100 dark:border-primary-800 shadow-sm">
                <h4 className="font-bold text-primary-900 dark:text-neutral-100 text-lg mb-1">ISO Certifications</h4>
                <p className="text-primary-600 dark:text-neutral-400 text-sm">Achieved ISO 14001, building upon earlier ISO 9002 and DIN EN ISO 9001:2000 milestones.</p>
              </div>
            </div>

            <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
              <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-primary-200 text-primary-600 dark:text-neutral-400 shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 font-mono text-xs font-bold">
                2017
              </div>
              <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl bg-gray-50 dark:bg-primary-950 border border-gray-100 dark:border-primary-800 shadow-sm">
                <h4 className="font-bold text-primary-900 dark:text-neutral-100 text-lg mb-1">New Mold Factory</h4>
                <p className="text-primary-600 dark:text-neutral-400 text-sm">Established a dedicated mold manufacturing facility to expand robust in-house capabilities.</p>
              </div>
            </div>
            
             <div className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
              <div className="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-accent text-white shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 font-mono text-xs font-bold ring-4 ring-accent-soft">
                2021
              </div>
              <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] p-6 rounded-2xl bg-accent-soft border border-blue-100 shadow-sm">
                <h4 className="font-bold text-blue-900 text-lg mb-1">25th Anniversary</h4>
                <p className="text-blue-800/80 text-sm">Celebrating 25 years of excellence and continuous contribution to the Philippine manufacturing industry.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Contact Section */}
      <section id="contact" className="py-24 bg-gray-50 dark:bg-primary-950 border-t border-gray-200 dark:border-primary-800">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-white dark:bg-primary-900 rounded-3xl shadow-xl shadow-primary-200/50 overflow-hidden border border-gray-100 dark:border-primary-800">
            <div className="grid md:grid-cols-2">
              {/* Info Panel */}
              <div className="bg-primary-900 text-white p-10 lg:p-14 flex flex-col justify-between">
                <div>
                  <h3 className="text-3xl font-bold mb-4">Let's build together.</h3>
                  <p className="text-primary-400 mb-12 text-lg">
                    Whether you need precise plastic components, new tooling arrays, or are looking to partner for high-volume orders, our team in Cavite is ready to help.
                  </p>
                  
                  <div className="space-y-6">
                    <div className="flex items-start">
                      <MapPin className="w-6 h-6 text-accent mr-4 shrink-0 mt-1" />
                      <div>
                        <h4 className="font-medium text-white mb-1">Headquarters & Factory</h4>
                        <p className="text-primary-400 text-sm leading-relaxed">
                          FCIE Industrial Estate<br/>
                          Dasmariñas, Cavite<br/>
                          Philippines
                        </p>
                      </div>
                    </div>
                    
                    <div className="flex items-center">
                      <Phone className="w-6 h-6 text-accent mr-4 shrink-0" />
                      <p className="text-primary-300">Available upon request</p>
                    </div>
                  </div>
                </div>
                
                <div className="mt-16 pt-8 border-t border-primary-800">
                  <p className="text-primary-500 dark:text-neutral-400 text-sm font-medium uppercase tracking-widest mb-4">Operations Headed By</p>
                  <p className="text-white font-medium">Hiroaki Onoe <span className="text-primary-500 dark:text-neutral-400 font-normal">| President</span></p>
                </div>
              </div>

              {/* Form Panel */}
              <div className="p-10 lg:p-14 bg-white dark:bg-primary-900">
                {formStatus === 'success' ? (
                  <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="h-full flex flex-col items-center justify-center text-center space-y-4">
                    <div className="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-2">
                      <CheckCircle2 className="w-8 h-8" />
                    </div>
                    <h3 className="text-2xl font-bold text-primary-900 dark:text-neutral-100">Message Sent!</h3>
                    <p className="text-primary-500">Thank you for reaching out. Our Philippine office will get back to you shortly.</p>
                    <button onClick={() => setFormStatus('idle')} className="mt-4 text-accent font-medium hover:text-accent/90">
                      Send another message
                    </button>
                  </motion.div>
                ) : (
                  <form onSubmit={handleContactSubmit} className="space-y-6">
                    <h3 className="text-2xl font-bold text-primary-900 dark:text-neutral-100 mb-6">Send an Inquiry</h3>
                    <div className="grid grid-cols-2 gap-6">
                      <div className="col-span-2 sm:col-span-1">
                        <label className="block text-sm font-medium text-primary-700 mb-2">First Name</label>
                        <input required type="text" className="w-full px-4 py-3 bg-gray-50 dark:bg-primary-950 border border-gray-200 dark:border-primary-800 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent outline-none transition-all placeholder:text-gray-400" placeholder="Juan" />
                      </div>
                      <div className="col-span-2 sm:col-span-1">
                        <label className="block text-sm font-medium text-primary-700 mb-2">Last Name</label>
                        <input required type="text" className="w-full px-4 py-3 bg-gray-50 dark:bg-primary-950 border border-gray-200 dark:border-primary-800 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent outline-none transition-all placeholder:text-gray-400" placeholder="Dela Cruz" />
                      </div>
                      <div className="col-span-2">
                        <label className="block text-sm font-medium text-primary-700 mb-2">Company Email</label>
                        <input required type="email" className="w-full px-4 py-3 bg-gray-50 dark:bg-primary-950 border border-gray-200 dark:border-primary-800 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent outline-none transition-all placeholder:text-gray-400" placeholder="juan@company.com" />
                      </div>
                      <div className="col-span-2">
                        <label className="block text-sm font-medium text-primary-700 mb-2">Message Subject</label>
                        <select className="w-full px-4 py-3 bg-gray-50 dark:bg-primary-950 border border-gray-200 dark:border-primary-800 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent outline-none transition-all text-primary-900 dark:text-neutral-100">
                          <option>Plastic Injection Products</option>
                          <option>Mold Setup / Tooling</option>
                          <option>General Inquiry</option>
                          <option>Careers</option>
                        </select>
                      </div>
                      <div className="col-span-2">
                        <label className="block text-sm font-medium text-primary-700 mb-2">Your Message</label>
                        <textarea required rows={4} className="w-full px-4 py-3 bg-gray-50 dark:bg-primary-950 border border-gray-200 dark:border-primary-800 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent outline-none transition-all placeholder:text-gray-400 resize-none" placeholder="How can we assist you?"></textarea>
                      </div>
                    </div>
                    <button type="submit" disabled={formStatus === 'submitting'} className="w-full py-4 px-6 bg-accent hover:bg-accent/90 text-white font-medium rounded-lg shadow-md shadow-accent/20 transition-all flex justify-center items-center disabled:opacity-70 disabled:cursor-not-allowed">
                      {formStatus === 'submitting' ? 'Sending...' : 'Send Message'}
                    </button>
                    <p className="text-xs text-primary-500 dark:text-neutral-400 text-center mt-4">
                      By submitting this form, you agree to our Privacy Policy regarding data protection.
                    </p>
                  </form>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-primary-900 py-12 border-t border-primary-900 text-center text-primary-500 dark:text-neutral-400 text-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center">
          <div className="flex items-center space-x-3 mb-4 md:mb-0 opacity-80 hover:opacity-100 transition-opacity">
            <img src="/build/logo.svg" alt="Ogami ERP" className="h-6 w-auto grayscale brightness-200" />
          </div>
          <p>© {new Date().getFullYear()} Ogami Co., Ltd. / Philippines Ogami Corp. All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}
