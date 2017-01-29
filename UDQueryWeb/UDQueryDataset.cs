using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;

namespace UDQueryWeb
{
    [Serializable]
    public class UDQueryDataset
    {
        public List<UDQueryRecord> Records { get; set; }
    }
}
